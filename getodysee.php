<?
function getodysee($link) {
  $request = '{"jsonrpc":"2.0","method":"resolve","params":{"urls":["lbry://'.str_replace(':','#',$link).'"],"include_is_my_output":true,"include_purchase_receipt":true},"id":0}';
  
  $ch = curl_init('https://api.na-backend.odysee.com/api/v1/proxy?m=resolve');
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  //curl_setopt($ch, CURLOPT_HEADER, false);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
  curl_setopt($ch, CURLOPT_POST, 1);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $request);
  curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'Content-Type:application/json; charset=utf-8'
    ));
  $response = curl_exec($ch);
  $err     = curl_errno( $ch );
  $errmsg  = curl_error( $ch );
  $header  = curl_getinfo( $ch );
  curl_close($ch);

  $channelid = json_decode($response,true);
  foreach ($channelid['result'] AS $lbry=>$lbrydata) {
    $data[$lbry] = array(
      'claim_id'=>$lbrydata['claim_id'],
      'title'=>(strlen(trim($lbrydata['value']['title']))>0?$lbrydata['value']['title']:$lbrydata['name']),
      'upload_cnt'=>$lbrydata['meta']['claims_in_channel'],
      'link'=>str_replace("#",":",substr($lbrydata['canonical_url'],7)),
      'desc'=>$lbrydata['value']['description'],
      'file_cnt'=>0,
      'video_cnt'=>0,
      'other_cnt'=>0,
      'lastupload'=>0,
      'uploads'=>array(),
    );

    $pages = ceil($lbrydata['meta']['claims_in_channel']/50);
    for ($i=1;$i<=$pages;$i++) {
      $request = '{
        "jsonrpc": "2.0",
        "method": "claim_search",
        "params": {
          "page_size": 50,
          "page": '.$i.',
          "claim_type": [
            "stream"
          ],
          "no_totals": true,
          "not_tags": [],
          "order_by": [
            "release_time"
          ],
          "has_source": true,
          "channel_ids": [
            "'.$lbrydata['claim_id'].'"
          ]
        },
        "id": 0
      }';

      $ch = curl_init('https://api.na-backend.odysee.com/api/v1/proxy?m=claim_search');
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      //curl_setopt($ch, CURLOPT_HEADER, false);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
      curl_setopt($ch, CURLOPT_POST, 1);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $request);
      curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type:application/json; charset=utf-8'
        ));
      $response = curl_exec($ch);
      $err     = curl_errno( $ch );
      $errmsg  = curl_error( $ch );
      $header  = curl_getinfo( $ch );
      curl_close($ch);

      $claimsearch = json_decode($response,true);
      foreach ($claimsearch['result']['items'] AS $item) {
        $tmp = array(
          'claim_id'=>$item['claim_id'],
          'title'=>$item['value']['title'],
          'release'=>$item['timestamp'],
          'release_format'=>date("m/d/Y",$item['timestamp']),
          'link'=>str_replace("#",":",substr($item['canonical_url'],7)),
          'type'=>$item['value']['stream_type'],
          'thumb'=>$item['value']['thumbnail']['url'],
          'desc'=>$item['value']['description'],
          'file'=>$item['value']['source']['name'],
          'size'=>$item['value']['source']['size'],
          'tags'=>$item['value']['tags'],
          'media_type'=>$item['value']['source']['media_type'],
          'video_length'=>$item['value']['video']['duration'],
          'cost'=>$item['value']['fee']['amount'],
        );
        switch ($item['value']['stream_type']) {
          default:
            $data[$lbry]['other_cnt'] ++;
            break;
          case "video":
            $data[$lbry]['video_cnt'] ++;
            break;
          case "model":
          case "binary":
            $data[$lbry]['file_cnt'] ++;
            break;
        }
        if ($data[$lbry]['lastupload']==0 || $item['value']['release_time'] < $data[$lbry]['lastupload']) {
          $data[$lbry]['lastupload'] = $item['timestamp'];
          $data[$lbry]['lastupload_format'] = date("m/d/Y",$item['timestamp']);
        }
        
        $data[$lbry]['uploads'][] = $tmp;
      }
    }
  }

  return $data;
}
?>