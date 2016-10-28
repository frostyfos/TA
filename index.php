<!DOCTYPE html>
<?php 
	error_reporting(0);
	set_time_limit(0);
?>
<html>
<html>
<head>
	<meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Automatic Text Summarization</title>
	<!-- Bootstrap -->
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/custom.css" rel="stylesheet">
    <link href="css/bootstrap.vertical-tabs.css" rel="stylesheet">
</head>
<body>
	<?php
	include "./function.php"; 
	include "./svd.php"; 

	$stopWords = file_get_contents("stopwords_id.txt");
	//koneksi database
	connectDB();

	//init
	$pisahKalimat = [];
	$caseFolding = [];
	$tokenizing = [];
	$stopwordsRemoved = [];
	$hasilStem = [];
	$tf = [];
	$df = [];
	$w = []; //nilai tfidf (weight)
	$daftarKata = [];
	$dummy = [];
	$matrix = [];

	$url = $_POST["link"];
	
	$content = getContent($url);

	if (!empty($content)) {
		// $content = preg_replace('/^viva.co.id/',"",$content);
		// $content = trim($content );
		//hapus viva.co.id di awal
		$content = substr($content, 13);
		$content = trim($content );

		$compression = $_POST["compression"];
		$compression = $compression/100;

		//panggil fungsi prepos
		$pisahKalimat = pecahkalimat($content); 
		$caseFolding = caseFolding($pisahKalimat);
		$tokenizing = tokenizing($caseFolding);
		$stopwordsRemoved = stopwordsRemoval($stopWords,$tokenizing);
		
		//count
		$panjang = count($pisahKalimat);
		$banyakKata = count($tokenizing);
		$banyakKataMS = count($stopwordsRemoved);

		for ($i=0; $i < count($stopwordsRemoved) ; $i++) { 
			for ($j=0; $j < count($stopwordsRemoved[$i]) ; $j++) { 
				$stemVal = stem_NaziefAndriani($stopwordsRemoved[$i][$j]);
				$hasilStem[$i][] = $stemVal;
			}
		}
		$tf = getFrequency($hasilStem);
		$df = getDF($hasilStem);
		$w = tfidf($tf,$df,$hasilStem);	

		//hasil ringkasan
		$daftarKata = daftarKata($w);
		$dummy = createDummyMatrix($daftarKata,$hasilStem); 
		$matrix = createMatrix($dummy,$w,$daftarKata);
		$matrix = matrixTranspose($matrix);
	}else
		echo "tes";
	?>
	<!-- navbar -->
	<nav class="navbar navbar-default navbar-static-top">
	    <div class="container">
		    <div class="navbar-header"><a class="navbar-brand" href="index.php">Automatic Text Summarization</a></div>
	    </div>
	</nav>
	<div class="row">
		<!-- tab -->
		<div class="col-xs-3">
			<div class="setting">
			<button type="button" class="btn btn-primary" data-toggle="modal" data-target="#myModal">
			  	Pilih Berita
			</button>

			<!-- Modal -->
			<div class="modal fade" id="myModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
			  	<div class="modal-dialog" role="document">
			    	<div class="modal-content">
			      		<div class="modal-header">
			        		<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
			        		<h3 class="modal-title" id="myModalLabel">Silahkan Pilih Berita Yang Akan Di Ringkas</h3>
			      		</div>
			     		<div class="modal-body">
			       		<?php 
				        $doc = new DOMDocument();
						$doc->load('http://rss.viva.co.id/get/politik');
						$arrFeeds = array();

						$counter = 0; //buat batas feed
						
						?>
							<form action="index.php" method="POST">
							<?php
							foreach ($doc->getElementsByTagName('item') as $node) {
							    if($counter == 15 ) {
							       break;
							     }    
							    $itemRSS = array ( 
							      'title' => $node->getElementsByTagName('title')->item(0)->nodeValue,
							      'link' => $node->getElementsByTagName('link')->item(0)->nodeValue
							      );
							      $counter++;
							
							?>  
								<button class="berita" type="submit" name="link" value="<?php echo $itemRSS['link']; ?>" >
									<?php echo $itemRSS['title']; ?>
								</button><br/>
								<input type="hidden" name="title" value="<?php echo $itemRSS['title']; ?>" >
							<?php 
								} 
								if (count($itemRSS['title'])==0)
									echo '<div class="alert alert-danger text-center" role="alert">
										<h5><strong>Daftar Berita Tidak Bisa di Tampilkan, Mohon Cek Kembali Koneksi Internet Anda</strong></h5>
									</div>';
							?>
							<h3>Compression Rate</h3>
							<input type="range" id="slider" min="1" max="100" value="<?php echo !empty($compression)? $compression : '';?>" step="1" style="width:100%;" onchange="printValue('slider','rangeValue')">
							<input type="text" id="rangeValue" name="compression" value="<?php echo !empty($compression)? $compression : '50';?>" style="width:35px;" />
				        	</form>
			      		</div>
			      		<div class="modal-footer">
			        		<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
			      		</div>
			   		</div>
			  	</div>
			</div>
		</div>
	    <!-- Nav tabs -->
		    <ul class="nav nav-tabs tabs-left">
		      	<li class="active"><a href="#hasil" data-toggle="tab">Hasil Ringkasan</a></li>
		      	<li><a href="#kalimat" data-toggle="tab">Pemecahan Kalimat</a></li>
		      	<li><a href="#case" data-toggle="tab">Case Folding</a></li>
		      	<li><a href="#token" data-toggle="tab">Tokenizing</a></li>
		      	<li><a href="#stopwords" data-toggle="tab">Stop Words Removal</a></li>
		      	<li><a href="#stem" data-toggle="tab">Stemming</a></li>
		      	<li><a href="#tfidf" data-toggle="tab">Pembobotan Kata</a></li>
		    </ul>
		</div>
		<div class="col-xs-9">
		    <!-- Tab panes -->
		    <div class="tab-content">
<!-- .................................hasil ringakasan .................................................-->
		    	<div class="tab-pane active" id="hasil">
		      		<h2>Hasil Ringkasan</h2><hr><br>
		      		<?php 

		      		if (!empty($content)) {
						$svd = SVD($matrix);
	   					$avg = cariAvg($svd['V']);
						$hasilOlah = olahSVD($svd['V'],$avg);
						$length = cariLength($hasilOlah,$svd['S']);
						$sorted = $length;
						arsort($sorted);
						$kalimatRingkasan = array_replace($sorted, $pisahKalimat);
						$maxSententce = floor(count($pisahKalimat) * $compression);
		   				$sentenceCounter = 0;
   					}
		      		?>		
		      		<div>
						<!-- Nav tabs -->
						<ul class="nav nav-tabs" role="tablist">
					    	<li role="presentation" class="active"><a href="#dokumenAsli" aria-controls="dokumenAsli" role="tab" data-toggle="tab">Dokumen Asli</a></li>
					    	<li role="presentation"><a href="#hasilRingkasan" aria-controls="hasilRingkasan" role="tab" data-toggle="tab">Hasil Ringkasan</a></li>
					  	</ul>

					  <!-- Tab panes -->
					  	<div class="tab-content">
					    	<div role="tabpanel" class="tab-pane active" id="dokumenAsli">
					    	<?php  
					    	echo"<div id='box'>";
		   					foreach ($pisahKalimat as $key => $value) {
		   						echo '<p>'; echo $value; echo'</p>';
		   					}
		   					echo'</div>';
					    	?>
					    	</div>
					    	<div role="tabpanel" class="tab-pane" id="hasilRingkasan">
					    	<?php 
							echo"<div id='box'>";

		   					foreach ($kalimatRingkasan as $key => $value) {
		   						if($sentenceCounter == $maxSententce ) {
									break;
								}
		   						echo '<p>'; echo $value; echo'</p>';
		   						$sentenceCounter++;
		   					}
		   					if (count($value)==0)
		   						echo '<div class="alert alert-danger text-center" role="alert">
		   							<h4><strong>Hasil Ringkasan Tidak Bisa di Tampilkan</strong></h4>
		   							<h5><strong>Mohon Pilih Berita untuk di Ringkas atau Cek Kembali Koneksi Internet Anda</strong></h5>
		   						</div>';
   							echo'</div>';
					    	?>
					    	</div>
					  	</div>
					</div>
		      	</div>
<!-- .................................end hasil ringakasan .................................................-->
<!-- .................................pecah kalimat.................................................-->
		      	<div class="tab-pane" id="kalimat">
		      		<h2>Hasil Pecah Kalimat</h2><hr><br>
		      		<?php //print_r($pisahKalimat); ?>
		      		<table class="table table-bordered">
			      		<tr align="center" class="active">
			      			<td>Kalimat ke-</td>
			      			<td>Isi Kalimat</td>
			      		</tr>
						<?php for ($i=0; $i < $panjang ; $i++) { ?>
						<tr>
							<td><?php echo $i+1; ?></td>
							<td><?php echo $pisahKalimat[$i]; ?></td>
						</tr>
						<?php } ?>
					</table>
		      	</div>
<!-- .............................end pemisahan kalimat .................................................-->
<!-- ........................................case folding ..............................................-->
		      	<div class="tab-pane" id="case">
			      	<h2>Hasil Case Folding</h2><hr><br>
			      	<?php //print_r($caseFolding); ?>
			      	<table align="center" class="table table-bordered">
			      		<tr align="center" class="active">
				      		<td>Kalimat ke-</td>
				      		<td>Isi Kalimat</td>
				      	</tr>
						<?php for ($i=0; $i < $panjang ; $i++) { ?>
						<tr>
							<td><?php echo $i+1; ?></td>
							<td><?php echo $caseFolding[$i]; ?></td>
						</tr>
						<?php } ?>
					</table>
		      	</div>
<!-- ....................................end case folding ..............................................-->
<!-- ........................................ tokenisasi. ..............................................-->
		      	<div class="tab-pane" id="token">
			      	<h2>Hasil Tokenizing</h2><hr><br>
			      	<?php //print_r($tokenizing); ?>
			      	<table class="table table-bordered">
			      		<tr class="active" align="center">
			      			<td>Kalimat ke-</td>
				      		<td colspan="3">Kata Dalam Kalimat</td>
				      	</tr>
						<?php for ($i=0; $i < $banyakKata ; $i++) { ?>
						<tr>
							<td><?php echo $i+1; ?></td>
							<?php 	
							echo "<td>";
							for ($j=0; $j < count($tokenizing[$i]); $j++) { 
								echo $tokenizing[$i][$j] . " | ";
							}
							echo "</td>";	
							?>
						</tr>
						<?php } ?>
					</table>
		      	</div>
<!-- .....................................end tokenisasi. ..............................................-->
<!-- ........................................ stop words. ..............................................-->
		      	<div class="tab-pane" id="stopwords">++
			      	<h2>Hasil Stop Words Removal</h2><hr><br>
			      	<?php //print_r($stopwordsRemoved); ?>
			      	<table class="table table-bordered">
			      		<tr class="active" align="center">
			      			<td>Kalimat ke-</td>
				      		<td colspan="3">Kata Dalam Kalimat</td>
				      	</tr>
						<?php for ($i=0; $i < $banyakKata ; $i++) { ?>
						<tr>
							<td><?php echo $i+1; ?></td>
							<?php 	
							echo "<td>";
							for ($j=0; $j < count($stopwordsRemoved[$i]); $j++) { 
								echo $stopwordsRemoved[$i][$j] . " | ";
							}
							echo "</td>";	
							?>
						</tr>
						<?php } ?>
					</table>
		      	</div>
<!-- ........................................ end stop words. ..............................................-->
<!-- ........................................ stem. ..............................................-->
		      	<div class="tab-pane" id="stem">
			      	<h2>Hasil Stemming</h2><hr><br>
			      	<?php //print_r($hasilStem); ?>
			      	<table class="table table-bordered">
			      		<tr class="active" align="center">
			      			<td>Kalimat ke-</td>
				      		<td colspan="3">Kata Dalam Kalimat</td>
				      	</tr>
						<?php for ($i=0; $i < count($hasilStem) ; $i++) { ?>
						<tr>
							<td><?php echo $i+1; ?></td>
							<?php 	
							echo "<td>";
							for ($j=0; $j < count($hasilStem[$i]); $j++) { 
								echo $hasilStem[$i][$j] . " | ";
							}
							echo "</td>";
							?>
						</tr>
						<?php } ?>
					</table>
		      	</div>
<!-- ........................................ end stem. ..............................................-->
<!-- ........................................ tf idf. ..............................................-->
		    	<div class="tab-pane" id="tfidf">
			    	<h2>Hasil Pembobotan Kata</h2><hr><br>
			    	<?php echo "N atau Jumlah Kalimat = " . count($hasilStem); ?>
			    	<table class="table table-bordered">
			      		<tr class="active" align="center">
			      			<td>Kata</td>
			      			<td>Term Frequency (TF)</td>
			      			<td>Document Frequency (DF)</td>
			      			<td>IDF = log(N/df)</td>
			      			<td>Weight = TF*IDF</td>
				      	</tr>
						<?php 
						foreach ($w as $key => $value) {
						?>
						<tr>
							<td><?php echo $key; ?></td>
							<td><?php echo $tf[$key]; ?></td>
							<td><?php echo $df[$key]; ?></td>
							<td><?php echo log10(count($hasilStem)/$df[$key]); ?></td>
							<td><?php echo $value; ?></td>
						</tr>
						<?php } ?>
					</table>
		    	</div>
		    </div>
		</div> 
	</div>
<!-- ........................................ end tfidf. ..............................................-->
	<script>
		function printValue(sliderID, textbox) {
	        var x = document.getElementById(textbox);
	        var y = document.getElementById(sliderID);
	        x.value = y.value;
        }
    </script>
	<script src="js/jquery-2.2.2.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
</body>
</html>