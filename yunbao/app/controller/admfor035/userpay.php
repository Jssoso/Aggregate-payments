<?php
namespace WY\app\controller\admfor035;

use WY\app\libs\Controller;
use WY\app\model\Paybank;
if (!defined('WY_ROOT')) {
    exit;
}
class userpay extends CheckAdmin
{
    public function index()
    {
        $kw = $this->req->get('kw');
        $fdate = $this->req->get('fdate');
        $tdate = $this->req->get('tdate');
        $is_state = $this->req->get('is_state');
        $is_state = isset($_GET['is_state']) ? $is_state : -1;
        $cons = 'is_agent=?';
        $consOR = '';
        $consArr = array(0);
        if ($kw) {
            $users = $this->model()->select('id')->from('users')->where(array('fields' => 'username like ?', 'values' => array('%' . $kw . '%')))->fetchRow();
            if ($users) {
                $consOR .= $consOR ? ' or ' : '';
                $consOR .= 'userid = ?';
                $consArr[] = $users['id'];
            }
        }
        if ($is_state >= 0) {
            $cons .= $cons ? ' and ' : '';
            $cons .= 'is_state=?';
            $consArr[] = $is_state;
        }
        if ($kw) {
            $consOR .= $consOR ? ' or ' : '';
            $consOR .= 'userid = ?';
            $consArr[] = $kw;
        }
        if ($kw) {
            $consOR .= $consOR ? ' or ' : '';
            $consOR .= 'sn = ?';
            $consArr[] = $kw;
        }
        if ($fdate) {
            $cons .= $cons ? ' and ' : '';
            $cons .= 'addtime>=?';
            $consArr[] = strtotime($fdate);
        }
        if ($tdate) {
            $cons .= $cons ? ' and ' : '';
            $cons .= 'addtime<=?';
            $consArr[] = strtotime($tdate . ' 23:59:59');
        }
        if ($consOR) {
            $cons .= $cons ? ' and ' : '';
            $cons .= '(' . $consOR . ')';
        }
        $orderby = 'id desc';
        $sort = $this->req->get('sort');
        $sort = isset($_GET['sort']) ? $sort : 0;
        $by = $this->req->get('by');
        if ($by) {
            $sort2 = $sort ? ' desc' : ' asc';
            $orderby = $by . $sort2;
        }
        $page = $this->req->get('p');
        $page = $page ? $page : 1;
        $pagesize = 20;
        $totalsize = $this->model()->select()->from('payments')->where(array('fields' => $cons, 'values' => $consArr))->count();
        $lists = array();
        if ($totalsize) {
            $totalpage = ceil($totalsize / $pagesize);
            $page = $page > $totalpage ? $totalpage : $page;
            $offset = ($page - 1) * $pagesize;
            $lists = $this->model()->select()->from('payments')->offset($offset)->limit($pagesize)->orderby($orderby)->where(array('fields' => $cons, 'values' => $consArr))->fetchAll();
        }
        $pagelist = $this->page->put(array('page' => $page, 'pagesize' => $pagesize, 'totalsize' => $totalsize, 'url' => '?is_state=' . $is_state . '&kw=' . $kw . '&fdate=' . $fdate . '&tdate=' . $tdate . '&sort=' . $sort . '&by=' . $by . '&p='));
        $data = array('title' => '付款记录', 'lists' => $lists, 'pagelist' => $pagelist, 'search' => array('kw' => $kw, 'fdate' => $fdate, 'tdate' => $fdate, 'is_state' => $is_state), 'sort' => $sort, 'by' => $by);
        $this->put('payments.php', $data);
    }
    public function pay()
    {
        $id = isset($this->action[3]) ? intval($this->action[3]) : 0;
        $data = $this->model()->select()->from('payments')->where(array('fields' => 'id=?', 'values' => array($id)))->fetchRow();
 
		$this->put('paymentsinfo.php', $data);
		
    }
    public function savepay()
    {
        $id = isset($this->action[3]) ? intval($this->action[3]) : 0;
        $data = isset($_POST) ? $_POST : false;
        if ($id && $data && $data['is_state'] == '1') 
		{
            $payments = $this->model()->select()->from('payments')->where(array('fields' => 'id=?', 'values' => array($id)))->fetchRow();
            $resCode = '';
            if ($payments['retmsg']) 
			{
                $ret = json_decode($payments['retmsg'], true);
                $tradeStatus = $ret['tradeStatus'];
            }
            if ($data['ptype'] == '1' && $tradeStatus != '3') 
			{
                $cfo = $this->model()->select()->from('cfo')->where(array('fields' => 'id=?', 'values' => array($data['cfoid'])))->fetchRow();
                if ($cfo) 
				{
                    $money = number_format($data['money'] - $data['fee'], 2, '.', '');
                    $cfo += array('sn' => 'b' . time() + mt_rand(1000, 9999), 'money' => $money);
                
					
					$tjurl = 'http://43.249.29.218/cspay/api/v1/dftransRequest';
					$key = 'c0f28c3f2fac5b15597bbdb4f5ddf918';
					$data2 = array();
					$data2['mch_id'] = '840000000226';
					$data2['trans_money'] = $cfo['money'] * 100;
					$data2['service'] = 'SAND_DF';
					$data2['out_trade_no'] =  $cfo['id'].'A'.date("YmdHis").mt_rand(1000, 9999);
					$data2['account_name'] =  $cfo['accountname'];
					$data2['bank_card'] =  $cfo['cardno'];
					$data2['bank_name'] =  $cfo['bankname'];
					$data2['bank_linked'] =  '100000000000';
					$data2['sign'] = $this->daifu_get_sign( $data2, $key );
					$post_data = http_build_query( $data2 );
				
					$json = $this->daifu_cpost( $tjurl, $post_data );
					$ret = json_decode( $json, true );
                    $this->model()->from('payments')->updateSet(array('retmsg' => json_encode($ret)))->where(array('fields' => 'id=?', 'values' => array($id)))->update();
                    if (  $ret['tradeStatus'] != 3 ) 
					{
                        $this->put('woodyapp.php', array('msg' => '代付接口返回：' . $ret['tradeMessage'], 'url' => $this->dir . 'userpay'));
                        exit;
                    }
                }
            }
        }
        $data += array('lastime' => time());
        if ($this->model()->from('payments')->updateSet($data)->where(array('fields' => 'id=?', 'values' => array($id)))->update()) {
            $this->put('woodyapp.php', array('msg' => '账单信息已保存成功', 'url' => $this->dir . 'userpay'));
        }
        $this->put('woodyapp.php', array('msg' => '账单信息已保存失败', 'url' => $this->dir . 'userpay'));
    }
	
	function daifu_get_sign( $data, $key  )
	{
		ksort( $data );
		$str = '';
		foreach( $data as $k => $v )
		{
			 if( $k != 'sign' && !empty( $v )  )
			 {
				 $str .= ( $k.'='.$v.'&');
			 }
		}
		$str .= "key=".$key;
		return strtoupper( md5(  $str ) );
	}

	function daifu_cpost( $tjurl, $post_data )
	{
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $tjurl);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HEADER, 0 );
		curl_setopt($ch, CURLOPT_POST, 1 );
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data );
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // 跳过证书检查
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); 
		$output = curl_exec($ch);
		curl_close($ch);
		return ($output);
	}
    public function upset()
    {
        $id = isset($this->action[3]) ? intval($this->action[3]) : 0;
        $this->model()->from('payments')->updateSet(array('is_state' => 1))->where(array('fields' => 'id=?', 'values' => array($id)))->update();
        $this->res->redirect($this->req->server('HTTP_REFERER'));
    }
}