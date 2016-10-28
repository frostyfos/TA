<?php
//function
function connectDB (){
	$server = "localhost";
	$usernName = "root";
	$password = "";

	$connect = mysqli_connect($server,$usernName,$password);
	if (!$connect) {
		die('Could not connect: ' . mysqli_error());
	}
}
function getContent($url){
		$content = "";
		$doc = new DOMDocument;
		$doc->preserveWhiteSpace = FALSE;

		@$doc->loadHTMLFile($url);
		$anchor_tags = $doc->getElementsByTagName('p');
		foreach ($anchor_tags as $tag) {
		    $content = $content . strtolower($tag->nodeValue);
		}
		return $content;
	}

function pecahkalimat($input){
	//split konten per kalimat
	$pisahKalimat = array();
	$hasilPisah = array();
	$pisahKalimat = preg_split("/[.!?]+/", $input);
	$pisahKalimat = array_slice($pisahKalimat, 0, sizeof($pisahKalimat)-1); // buang array terakhir (kosong)
	for ($i=0; $i < count($pisahKalimat); $i++) { 
		array_push($hasilPisah, $pisahKalimat[$i]);
	}
	return $pisahKalimat;

}

function caseFolding($pisahKalimat){
	$caseFolding = array();
	$caseFolding = array_map("strtolower", $pisahKalimat);
	$caseFolding = preg_replace("/[\d\W]+/"," ", $caseFolding);
	return $caseFolding;
}

function tokenizing($caseFolding){
	$tokenizing = array();
	$hasilTokenizing = array();
	for ($i=0; $i <count($caseFolding) ; $i++) { 
		$tokenizing = preg_split("/[\s]+/", $caseFolding[$i]);
		array_push($hasilTokenizing, $tokenizing);
	}

	//rapih rapih array
	$hasilTokenizing = array_map('array_filter', $hasilTokenizing);
	$hasilTokenizing = array_filter($hasilTokenizing);
	$hasilTokenizing = array_map('array_values', $hasilTokenizing);
	$hasilTokenizing = array_values($hasilTokenizing);
	return $hasilTokenizing;
}

function stopwordsRemoval($stopWords,$tokenizing){
	//pisah berdasarkan spasi
	$stopwordsRemoved = array();
	$hasilStopWordsRemoved = array();
	$getstopWords = preg_split("/[\s]+/", $stopWords);
	for ($i=0; $i < count($tokenizing); $i++) { 
		$stopwordsRemoved = array_diff($tokenizing[$i], $getstopWords);	
		$stopwordsRemoved = array_values($stopwordsRemoved); // perbaiki indeks
		array_push($hasilStopWordsRemoved, $stopwordsRemoved);
	}
	//rapih rapih array
	$hasilStopWordsRemoved = array_map('array_filter', $hasilStopWordsRemoved);
	$hasilStopWordsRemoved = array_filter($hasilStopWordsRemoved);
	$hasilStopWordsRemoved = array_map('array_values', $hasilStopWordsRemoved);
	$hasilStopWordsRemoved = array_values($hasilStopWordsRemoved);
	return $hasilStopWordsRemoved;
}

function stem_tesKataDasar($stopwordsRemoved){
	$connect = mysqli_connect('localhost','root','','ringkas');
	mysqli_select_db($connect,"ringkas");
	
	$query = "SELECT katadasar FROM tb_katadasar WHERE katadasar = '$stopwordsRemoved'";
	$result = mysqli_query($connect,$query) or die(mysqli_error());
	if(mysqli_num_rows($result)==1){
		return true;
	}else{
		return false;
	}
}

function stem_delInflectionSuffixes($kata){
	$kataAwal = $kata;
	$pattern = '([kl]ah$|[km]u$|nya$|pun$)'; //cari kata yang akhir -lah,kah,ku,mu,nya,pun
	if (preg_match($pattern, $kata)) {
		$kata = preg_replace($pattern, "", $kata);
		return $kata;
	}else
		return $kataAwal;
}

function stem_delDerivationSuffixes($kata){
	//cek dari hasil hapus inflection suffixes
	if (stem_tesKataDasar($kata)) {
			return $kata;
		}
	$kataAwal = $kata;

	if (preg_match("(i$)", $kata)) {
		$kata = preg_replace("(i$)", "", $kata);
		if (stem_tesKataDasar($kata))
			return $kata;
	}else if (preg_match("(an$)", $kata)) {
		$kata = preg_replace("(an$)", "", $kata);
		if (stem_tesKataDasar($kata))
				return $kata;
		else
		if (substr($kata, strlen($kata)-1)=='k') {
			$hapusK = $kata;
			$hapusK = preg_replace("(k$)", "", $kata);
			if (stem_tesKataDasar($hapusK))
				return $hapusK;
		}else
			return $kata;
	}

	return $kata;
}


function stem_delDerivationPrefixes($kata){
	$kataAwal = $kata;
	$_kata = "";
	//cek awalan dan akhiran yang dilarang
	// if (preg_match("(^be\w+i$)", $kata)) { //awalan be+kata+ akhiran i
	// 	return $kata;
	// }else if (preg_match("(^di\w+an$)", $kata)) { //awalan di+kata+ akhiran an
	// 	return $kata;
	// }else if (preg_match("(^ke\w+i$|kan$)", $kata)) { //awalan ke+kata+ akhiran i atau kan
	// 	return $kata;
	// }else if (preg_match("(^me\w+an$)", $kata)) { //awalan me+kata+ akhiran an
	// 	return $kata;
	// }else if (preg_match("(^se\w+i$|kan$)", $kata)) { //awalan se+kata+ akhiran i atau kan
	// 	return $kata;
	// }//end cek awalan akhiran terlarang

	//cek untuk awalan di/ke/se
	//var bantu untuk deteksi awalan

	$pattern1 = '(^di|^ke|^se)'; //cari kata yang awalnya di/ke/se
	if (preg_match($pattern1, $kata)) {
		$kata = preg_replace($pattern1, "", $kata);
		if (stem_tesKataDasar($kata))
			return $kata;
		// }else if(substr($hasilDi, 0,2)=="di"){ //stop jika awalan yang dideteksi sekarang sama dengan awalan yg dideteksi sebelumnya
		// 	return $kata;
		// }else if(substr($hasilKe, 0,2)=="ke"){
		// 	return $kata;
		// }else if(substr($hasilSe, 0,2)=="se"){
		// 	return $kata;
		// }
	}//end cek untuk awalan di/ke/se

	//var bantu untuk deteksi awalan
	// $hasilMi = preg_replace("/^me/", "", $kata);
	// $hasilBe = preg_replace("/^be/", "", $kata);
	// $hasilPe = preg_replace("/^pe/", "", $kata);
	// $hasilTe = preg_replace("/^te/", "", $kata);
	//cek untuk awalan me/be/pe/te , ikutin aturan tabel
	$pattern2 = '(^me|^be|^pe|^te)';
	if (preg_match($pattern2, $kata)) {
		//consonant [b-df-hj-np-tv-z]
		//RULE 1 FIX
		if (preg_match("(^be[r][aiueo])", $kata)) {
			$_kata = preg_replace("(^be[r])", "", $kata);
			if (stem_tesKataDasar($_kata)) {
				return $_kata;
			}else {
				$_kata = preg_replace("(^be)", "", $kata);
				if (stem_tesKataDasar($_kata)) 
					return $_kata;
			}
		}
		//RULE 2 FIX
		if (preg_match("(^be[r][b-df-hj-npqstv-z]\w[^e][^r])", $kata)) {
			$_kata = preg_replace("(^be[r])", "", $kata);
			if (stem_tesKataDasar($_kata))
				return $_kata;
		}

		//RULE 3 FIX
		if (preg_match("(^be[r][b-df-hj-npqstv-z]aer[aiueo])", $kata)) {
			$_kata = preg_replace("(^be[r])", "", $kata);
			if (stem_tesKataDasar($_kata))
				return $_kata;
		}

		//RULE 4 FIX
		if ($kata == "belajar") {
			$_kata = "ajar";
			return $_kata;
		}

		//RULE 5 FIX
		if (preg_match("(^be[b-df-hjkmnpqstv-z]er[b-df-hj-np-tv-z])", $kata)) {
			$_kata = preg_replace("(^be)", "", $kata);
			if (stem_tesKataDasar($kata))
				return $_kata;
		}//END RULE 5 FIX

		//RULE 6 FIX
		if (preg_match("(^te[r][aiueo])", $kata)) {
			$_kata = preg_replace("(^te)", "", $kata);
			if (stem_tesKataDasar($_kata)) {
				return $_kata;
			}else {
				$_kata = preg_replace("(^te[r])", "", $kata);
				if (stem_tesKataDasar($_kata))
					return $_kata;
			}
			
		}//END RULE 6 FIX

		//RULE 7 FIX
		if (preg_match("(^te[r][b-df-hj-npqstv-z]er[aiueo])", $kata)) { //awalan te-+r+konsonan kecuali r +er+vokal+any
			$_kata = preg_replace("(^te[r])", "", $kata);
			if (stem_tesKataDasar($kata))
				return $_kata;
		}//END RULE 7 FIX

		//RULE 8 FIX
		if (preg_match("(^te[r][b-df-hj-npqstv-z][^e][^r])", $kata)) { 
			$_kata = preg_replace("(^te[r])", "", $kata);
			if (stem_tesKataDasar($_kata))
				return $_kata;
		}//END RULE 8 FIX

		//RULE 9 FIX
		if (preg_match("(^te[b-df-hj-npqstv-z]er[b-df-hj-np-tv-z])", $kata)) { 
			$_kata = preg_replace("(^te)", "", $kata);
			if (stem_tesKataDasar($_kata))
				return $_kata;
		}//END RULE 9FIX

		//RULE 10 FIX
		if (preg_match("(^me[lrwy][aiueo])", $kata)) {
			$_kata = preg_replace("(^me)", "", $kata);
			if (stem_tesKataDasar($_kata))
				return $_kata;	
		}

		//RULE 11 FIX
		if(preg_match("(^mem[bfv])", $kata)){
			$_kata = preg_replace("(^mem)", "", $kata);
			if (stem_tesKataDasar($_kata))
				return $_kata;	
		}

		//RULE 12 FIX
		if(preg_match("(^mempe[rl])", $kata)){
			$_kata = preg_replace("(^mem)", "", $kata);
			if (stem_tesKataDasar($_kata))
				return $_kata;
		}

		//RULE 13 FIX
		if(preg_match("(^mem(([r][aiueo])|[aiueo]))", $kata)){
			$_kata = preg_replace("(^me)", "", $kata);
			if (stem_tesKataDasar($_kata))
				return $_kata;
			else{
					$_kata = preg_replace("(^mem)", "p", $kata);
					if (stem_tesKataDasar($_kata))
						return $_kata;
				}
		}

		//RULE 14 FIX
		if(preg_match("(^men[cdjz])", $kata)){
			$_kata = preg_replace("(^men)", "", $kata);
			if (stem_tesKataDasar($_kata))
				return $_kata;
		}

		//RULE 15 FIX
		if(preg_match("(^men[aiueo])", $kata)){
			$_kata = preg_replace("(^me)", "", $kata);
			if (stem_tesKataDasar($_kata))
				return $_kata;
			else{
					$_kata = preg_replace("(^men)", "t", $kata);
					if (stem_tesKataDasar($_kata))
						return $_kata;
				}	
		}

		//RULE 16 FIX
		if(preg_match("(^meng[ghq])", $kata)){
			$_kata = preg_replace("(^meng)", "", $kata);
			if (stem_tesKataDasar($_kata))
				return $_kata;
		}

		//RULE 17 FIX
		if(preg_match("(^meng[aiueo])", $kata)){
			$_kata = preg_replace("(^meng)", "", $kata);
			if (stem_tesKataDasar($_kata))
				return $_kata;
			else{
					$_kata = preg_replace("(^meng)", "k", $kataAwal);
					if (stem_tesKataDasar($_kata))
						return $_kata;
				}	
		}

		//RULE 18 FIX
		if(preg_match("(^meny[aiueo])", $kata)){
			$_kata = preg_replace("(^meny)", "s", $kata);
			if (stem_tesKataDasar($_kata))
				return $_kata;
		}

		//RULE 19 FIX
		if(preg_match("(^memp[aiuo])", $kata)){
			$_kata = preg_replace("(^mem)", "", $kata);
			if (stem_tesKataDasar($_kata))
				return $_kata;
		}

		//RULE 20 FIX
		if(preg_match("(^pe[wy][aiueo])", $kata)){
			$_kata = preg_replace("(^pe)", "", $kata);
			if (stem_tesKataDasar($_kata))
				return $_kata;	
		}

		//RULE 21 FIX
		if(preg_match("(^per[aiueo])", $kata)){
			$_kata = preg_replace("(^per)", "", $kata);
			if (stem_tesKataDasar($_kata))
				return $_kata;
			else{
					$_kata = preg_replace("(^pe)", "", $kata);
					if (stem_tesKataDasar($_kata))
						return $_kata;
				}	
		}

		//RULE 22 FIX
		if (preg_match("(^pe[r][b-df-hj-npqstv-z]\w[^e][^r])", $kata)) {
			$_kata = preg_replace("(^pe[r])", "", $kata);
			if (stem_tesKataDasar($_kata))
				return $_kata;
		}

		//RULE 23 FIX
		if (preg_match("(^pe[r][b-df-hj-npqstv-z]\w[e][r][aiueo])", $kata)) {
			$_kata = preg_replace("(^pe[r])", "", $kata);
			if (stem_tesKataDasar($_kata))
				return $_kata;
		}

		//RULE 24 FIX
		if (preg_match("(^pem[bfaiueo])", $kata)) {
			$_kata = preg_replace("(^pem)", "", $kata);
			if (stem_tesKataDasar($_kata))
				return $_kata;
		}

		//RULE 25 FIX
		if(preg_match("(^pem(([r][aiueo])|[aiueo]))", $kata)){
			$_kata = preg_replace("(^pe)", "", $kata);
			if (stem_tesKataDasar($_kata))
				return $_kata;
			else{
					$_kata = preg_replace("(^pem)", "p", $kata);
					if (stem_tesKataDasar($_kata))
						return $_kata;
				}
		}

		//RULE 26 FIX
		if (preg_match("(^pen[cdjz])", $kata)) {
			$_kata = preg_replace("(^pen)", "", $kata);
			if (stem_tesKataDasar($_kata))
				return $_kata;
		}

		//RULE 27 FIX
		if (preg_match("(^pen[aiueo])", $kata)) {
			$_kata = preg_replace("(^pe)", "", $kata);
			if (stem_tesKataDasar($_kata))
				return $_kata;
			else{
					$_kata = preg_replace("(^pen)", "t", $kata);
					if (stem_tesKataDasar($_kata))
						return $_kata;
				}
		}

		//RULE 28 FIX
		if (preg_match("(^peng[ghq])", $kata)) {
			$_kata = preg_replace("(^peng)", "", $kata);
			if (stem_tesKataDasar($_kata))
				return $_kata;
		}

		//RULE 29 FIX
		if (preg_match("(^peng[aiueo])", $kata)) {
			$_kata = preg_replace("(^peng)", "", $kata);
			if (stem_tesKataDasar($_kata))
				return $_kata;
			else{
					$_kata = preg_replace("(^peng)", "k", $kata);
					if (stem_tesKataDasar($_kata))
						return $_kata;
				}
		}

		//RULE 30 FIX
		if (preg_match("(^peny[aiueo])", $kata)) {
			$_kata = preg_replace("(^peny)", "s", $kata);
			if (stem_tesKataDasar($_kata))
				return $_kata;
		}

		//RULE 31 FIX
		if (preg_match("(^pel[aiueo])", $kata)) {
			$_kata = preg_replace("(^pe)", "", $kata);
			if (stem_tesKataDasar($_kata)){
				if ($_kata != "ajar") {
					return $_kata;
				}
			}
		}
		//consonant [b-df-hj-np-tv-z]
		//RULE 32 FIX
		if (preg_match("(^pe[b-df-hjkopqs-uvz][e][r][aiueo])", $kata)) {
			$_kata = preg_replace("(^pe)", "", $kata);
			if (stem_tesKataDasar($_kata)){
				return $_kata;
			}
		}

		//RULE 33 cari partikel kata FIX
		if (preg_match("(^pe[b-df-hjkopqs-uvz])", $kata)) {
			$_kata = preg_replace("(^pe)", "", $kata);
			if (stem_tesKataDasar($_kata)){
				return $_kata;
			}
		}
		
	}//end cek untuk awalan me/be/pe/te

}
//prep
function stem_NaziefAndriani($kata){
	$hasil = "";
	$i = 0;

	//var bantu deteksi kata awal
	$hasilDi = preg_replace("/^di/", "", $hasil);
	$hasilKe = preg_replace("/^ke/", "", $hasil);
	$hasilSe = preg_replace("/^se/", "", $hasil);
	$hasilMe = preg_replace("/^me/", "", $hasil);
	$hasilBe = preg_replace("/^be/", "", $hasil);
	$hasilPe = preg_replace("/^pe/", "", $hasil);
	$hasilTe = preg_replace("/^te/", "", $hasil);
	if (stem_tesKataDasar($kata)) {
		return $kata;
	}
	
	$hasil = stem_delInflectionSuffixes($kata);
	if (stem_tesKataDasar($hasil))
		return $hasil;

	$hasil = stem_delDerivationSuffixes($hasil);
	if (stem_tesKataDasar($hasil))
		return $hasil;

	$hasil = stem_delDerivationPrefixes($hasil);
		if (stem_tesKataDasar($hasil))
			return $hasil;
	return $kata;
}

function getFrequency($hasilStem){
	$frequency = []; //array baru untuk tampung
	foreach ($hasilStem as $key => $value) {
		foreach ($value as $key2 => $value2) {
			$needle = $value2; //key yang di cari
			if (array_key_exists($needle, $frequency))
				$frequency[$needle]++;
			else
				$frequency[$needle] = 1;
		}
	}
	return $frequency;
}	

function getDF($hasilStem){
	$df = [];
	$unique = array_map('array_unique', $hasilStem);
	foreach ($unique as $key => $value) {
		foreach ($value as $key2 => $value2) {
			if(array_key_exists($value2, $df))
				$df[$value2]++;
			else
				$df[$value2] = 1;
		}
	}
	return $df;
}

function tfidf($tf,$df,$hasilStem){
	$weight = [];
	foreach ($hasilStem as $key => $value) {
		foreach ($value as $key2 => $value2) {
			$weight[$value2] = $tf[$value2] * log10(count($hasilStem)/$df[$value2]) ;
		}
	}
	return $weight;
}

function daftarKata($w){
	$daftarKata = [];
	foreach ($w as $key => $value) {
		$daftarKata[] = $key;
	}
	return $daftarKata;
}

function createDummyMatrix($daftarKata,$hasilStem){
	$matrixDummy = [];
	for ($i=0; $i < count($hasilStem) ; $i++) { 
		for ($j=0; $j < count($daftarKata) ; $j++) { 
			$matrixDummy[$i][] = $daftarKata[$j];
		}
	}
	for ($i=0; $i < count($matrixDummy); $i++) { 
		for ($j=0; $j < count($matrixDummy[$i]) ; $j++) { 
			if (!in_array($matrixDummy[$i][$j], $hasilStem[$i])) {
				$matrixDummy[$i][$j] = 0;
			}
		}
	}
	return $matrixDummy;
}

function createMatrix($matrixDummy,$w,$daftarKata){
	$matrix = [];
	foreach ($matrixDummy as $key => $value) {
		foreach ($value as $key2 => $value2) {
			$dummy = $matrixDummy[$key][$key2];
			if (in_array($dummy, $daftarKata))
				$matrix[$key][$key2] = $w[$dummy];
			//ubah null jadi 0
			if (is_null($matrix[$key][$key2]))
				$matrix[$key][$key2] = 0;
		}
	}
	return $matrix;
}

function cariAvg($V){
	$avg = [];
	foreach ($V as $key => $value) {
		$avg[] = array_sum($V[$key]) / count($V[$key]);
	}
	return $avg;
}

function olahSVD($V,$avg){
	$hasilOlah = [];
	foreach ($V as $key => $value) {
		foreach ($value as $key2 => $value2) {
			if ($V[$key][$key2] <= $avg[$key])
				$hasilOlah[$key][$key2] = 0;
			else 
				$hasilOlah[$key][$key2] = $V[$key][$key2];
		}
	}
	return $hasilOlah;
}

function cariLength($hasilOlah,$S){
	$length = [];
	foreach ($hasilOlah as $key => $value) {
		foreach ($value as $key2 => $value2) {
			$length[$key] += (pow($hasilOlah[$key][$key2], 2) * pow($S[$key][$key], 2));
		}
		$length[$key] = sqrt($length[$key]);
	}
	return $length;
}
?>	