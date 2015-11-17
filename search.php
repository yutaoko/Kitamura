<?php

  /************************************************
   diary_tbから検索語でAND検索し、結果をJSONで返す
   ************************************************/

// データベース接続
$sqliteerror = null;
$db = new SQLite3('../../SQLite-data/kitamura.sqlite');

// POSTを受信してSQLエスケープ
$query = SQLite3::escapeString($_POST['query']); // 検索語
$offset = SQLite3::escapeString($_POST['offset']);
$searchwords = explode(" ", mb_convert_kana($query,'a'));

if(count($searchwords) == 0) { // 検索語がある場合
    $sql = "FROM diary_tb ";
 } else {
    $sql = "FROM diary_tb  WHERE ";
    $tmpsql = '';

    // 日記（日付・天気・本文）を検索語でAND検索する
    foreach($searchwords as $word) {
        if($tmpsql != '') {
            $tmpsql .= 'INTERSECT SELECT FROM diary_tb WHERE ';
        }
        $tmpsql .= "datejp LIKE '%$word%' OR weather LIKE '%$word%' OR body LIKE '%$word%' ";
    }
    $sql .= $tmpsql;
 }


//$countsql = "SELECT * " . $sql;
$countsql = "SELECT count(*) as cnt " . $sql;
$sql = "SELECT  * " . $sql . "ORDER BY date LIMIT $offset, 10";

//結果の総数を求める

//$num = $db->query($countsql);
////$total=sqlite_num_rows(sqlite_query($countsql, $db));

//$total = 0;

$count = $db->query($countsql);
$total = $count->fetchArray()['cnt'];
 
//while($result = $num->fetchArray()){
// $total++; 
//}

// 出力をUTF-8に
mb_http_output('UTF-8');

// 結果をJSON用に整形
$val = array("result" => array(), 
             "status" => array("offset" => $offset,"query" => $query),
             "total" => $total);

$data = $db->query($sql);
$ids = array();

while($aryCol = $data->fetchArray(SQLITE3_ASSOC)) {
    if($aryCol['links'] != '') {
        $links = explode(',',$aryCol['links']);

        // 場所リンクの場合、場所idを抜き出し保存
        foreach($links as $token) {
            if(preg_match('/^p(\d+)$/',$token)) {
                array_push($ids,$token);
            }
        }
    }
    
    array_push($val["result"], array("datejp" => $aryCol['datejp'],
                                     "weather" => $aryCol['weather'],
                                     "body" => $aryCol['body'],
                                     "description" => $aryCol['description'],
                                     "links" => $aryCol['links'],
                                     )
               );
 }

// 保存した場所IDをカンマ(,)区切り文字列に変換
$values = '';
foreach(array_unique($ids) as $id) {
    if($values != '')
        $values .= ',';
    $values .= substr($id, 1);
}

// 変換した文字列を使い各地図データを取得
$points = array();
if($values != '') { // 場所IDがあった場合
    $sql = "SELECT * FROM point_tb WHERE point_id IN ($values)";
    $tmp = $db->query($sql);
    while($point = $tmp->fetchArray(SQLITE3_ASSOC)) {
        array_push($points, array("point_id" => $point['point_id'],
                                  "name" => $point['name'],
                                  "comment" => $point['comment'],
                                  "link_url" => $point['link_url'],
                                  "lat" => $point['lat'],
                                  "lng" => $point['lng'],
                                  "image_name" => $point['image_name']
                                  )
                   );
    }
 }
$val['points'] = $points;

// JSON形式に変換して出力
echo json_encode($val);

?>
