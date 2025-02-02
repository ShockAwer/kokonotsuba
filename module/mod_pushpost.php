<?php
class mod_pushpost extends ModuleHelper {
	// 推文判斷起始標籤
	private $PUSHPOST_SEPARATOR = '[MOD_PUSHPOST_USE]';
	// 討論串最多顯示之推文筆數 (超過則自動隱藏，全部隱藏：0)
	private $PUSHPOST_DEF = 5;

	public function __construct($PMS) {
		parent::__construct($PMS);
		$this->loadLanguage(); // 載入語言檔
	}

	public function getModuleName() {
		return $this->moduleNameBuilder('文章推文機制');
	}

	public function getModuleVersionInfo() {
		return '7th.Release (v140529)';
	}



	public function autoHookHead(&$txt, $isReply) {
//如果不需要JQUERY可以COMMENT 第一行，第一行為自動決定加載JQUERY的JAVASCRIPT
		$txt .= '<script type="text/javascript">window.jQuery || document.write("\x3Cscript src=\x22//ajax.googleapis.com/ajax/libs/jquery/1.11.0/jquery.min.js\x22>\x3C/script>");</script>
<script type="text/javascript">
// <![CDATA[
var lastpushpost=0;
function mod_pushpostShow(pid){
	$g("mod_pushpostID").value = pid;
	$g("mod_pushpostName").value = getCookie("namec");
	$("div#mod_pushpostBOX").insertBefore($("div#r"+pid+" .quote"));

	if(lastpushpost!=pid) {
		$("div#mod_pushpostBOX").show();
	} else
		$("div#mod_pushpostBOX").toggle();
	lastpushpost = pid;
	return false;
}
function mod_pushpostKeyPress(e){if(e.which==13){e.preventDefault();mod_pushpostSend();}}
function mod_pushpostSend(){
	var o0 = $g("mod_pushpostID"), o1 = $g("mod_pushpostName"), o2 = $g("mod_pushpostComm"), o3 = $g("mod_pushpostSmb"), pp = $("div#r"+o0.value+" .quote");
	if(o2.value===""){ alert("'.$this->_T('nocomment').'"); return false; }
	o1.disabled = o2.disabled = o3.disabled = true;
	$.ajax({
		url: "'.str_replace('&amp;', '&', $this->getModulePageURL()).'&no="+o0.value,
		type: "POST",
		data: {ajaxmode: true, name: o1.value, comm: o2.value},
		success: function(rv){
			if(rv.substr(0, 4)!=="+OK "){ alert(rv); o3.disabled = false; return false; }
			rv = rv.substr(4);
			(pp.find(".pushpost").length===0)
				? pp.append("<div class=\'pushpost\'>"+rv+"</div>")
				: pp.children(".pushpost").append("<br />"+rv);
			o0.value = o1.value = o2.value = ""; o1.disabled = o2.disabled = o3.disabled = false;
			$("div#mod_pushpostBOX").hide();
		},
		error: function(){ alert("Network error."); o1.disabled = o2.disabled = o3.disabled = false; }
	});
}
// ]]>
</script>';
	}

	public function autoHookFoot(&$foot) {
		$foot .= '
<div id="mod_pushpostBOX" style="display:none">
<input type="hidden" id="mod_pushpostID" />'.$this->_T('pushpost').' <ul><li>'._T('form_name').' <input type="text" id="mod_pushpostName" maxlength="20" onkeypress="mod_pushpostKeyPress(event)" /></li><li>'._T('form_comment').' <input type="text" id="mod_pushpostComm" size="50" maxlength="50" onkeypress="mod_pushpostKeyPress(event)" /><input type="button" id="mod_pushpostSmb" value="'._T('form_submit_btn').'" onclick="mod_pushpostSend()" /></li></ul>
</div>
';
	}

	public function autoHookThreadPost(&$arrLabels, $post, $isReply) {
		$PIO = PMCLibrary::getPIOInstance();
		$pushcount = '';
		if ($post['status'] != '') {
			$f = $PIO->getPostStatus($post['status']);
			$pushcount = $f->value('mppCnt'); // 被推次數
		}

		$arrLabels['{$QUOTEBTN}'] .= '&nbsp;<a href="'.
			$this->getModulePageURL(array('no'=> $post['no'])).
			'" onclick="return mod_pushpostShow('.$post['no'].')">'.
			$pushcount.$this->_T('pushbutton').'</a>';
		if (strpos($arrLabels['{$COM}'], $this->PUSHPOST_SEPARATOR.'<br />') !== false) {
			// 回應模式
			if ($isReply || $pushcount <= $this->PUSHPOST_DEF) {
				$arrLabels['{$COM}'] = str_replace($this->PUSHPOST_SEPARATOR.
					'<br />', '<div class="pushpost">', $arrLabels['{$COM}']).
					'</div>';
			} else {
			// 頁面瀏覽
				// 定位符號位置
				$delimiter = strpos($arrLabels['{$COM}'], $this->PUSHPOST_SEPARATOR.'<br />');
				if ($this->PUSHPOST_DEF > 0) {
					$push_array = explode('<br />', substr($arrLabels['{$COM}'], $delimiter + strlen($this->PUSHPOST_SEPARATOR.'<br />')));
					$pushs = '<div class="pushpost">...<br />'.implode('<br />', array_slice($push_array, 0 - $this->PUSHPOST_DEF)).'</div>';
				} else {
					$pushs = '';
				}
				$arrLabels['{$COM}'] = substr($arrLabels['{$COM}'], 0, $delimiter).$pushs;
				$arrLabels['{$WARN_BEKILL}'] .= '<span class="warn_txt2">'.$this->_T('omitted').'<br /></span>'."\n";
			}
		}
	}

	public function autoHookThreadReply(&$arrLabels, $post, $isReply) {
		$this->autoHookThreadPost($arrLabels, $post, $isReply);
	}

	public function autoHookRegistBegin(&$name, &$email, &$sub, &$com, $upfileInfo, $accessInfo, $isReply) {
		// 登入權限允許標籤留存不轉換 (後端登入修改文章後推文仍有效)
		if (valid() < LEV_MODERATOR) return;

		// 防止手動插入標籤
		if (strpos($com, $this->PUSHPOST_SEPARATOR."\r\n") !== false) {
			$com = str_replace($this->PUSHPOST_SEPARATOR."\r\n", "\r\n", $com);
		}
	}

	public function autoHookAdminList(&$modFunc, $post, $isres) {
		$modFunc .= '[<a href="'.$this->getModulePageURL(
				array(
					'action' => 'del',
					'no' => $post['no']
				)
			).'">刪推</a>]';
	}

	public function ModulePage() {
		$PIO = PMCLibrary::getPIOInstance();

		if (!isset($_GET['no'])) die('[Error] not enough parameter.');
		if (isset($_GET['action'])) {
				$pushcount = ''; $puststart=0;
				$post = $PIO->fetchPosts($_GET['no']);
				if (!count($post)) die('[Error] Post does not exist.'); // 被推之文章不存在
				extract($post[0]);

				if ($status != ''){
					$f = $PIO->getPostStatus($status);
					$pushcount = $f->value('mppCnt'); // 被推次數
				}

				if (($puststart=strpos($com, $this->PUSHPOST_SEPARATOR.'<br />'))===false) die('[Error] No pushpost.');

				$ocom = substr($com,0,$puststart);
				$pushpost = explode('<br />',substr($com,$puststart+strlen($this->PUSHPOST_SEPARATOR.'<br />')));
				$com = $ocom;

				if ($_GET['action'] == 'del') { // list
					$p_count = 1;
					$com .= '<div class="pushpost">';
					foreach($pushpost as $p) {
						$com .= '<input type="checkbox" name="'.($p_count++).'" value="delete" />'.$p.'<br />';
					}
					$com .= '</div>';

					$dat = '';
					head($dat);
					$dat .= '<div class="bar_reply">'.$this->_T('deletepush').'</div>';
					$dat .= '<form action="'.$this->getModulePageURL(
						array(
							'action'=>'delpush',
							'no' => $_GET['no']
						)
					).'" method="post">';
					$dat .= PMCLibrary::getPTEInstance()->ParseBlock('SEARCHRESULT',
						array(
							'{$NO}'=>$no, '{$SUB}'=>$sub, '{$NAME}'=>$name,
							'{$NOW}'=>$now, '{$COM}'=>$com, '{$CATEGORY}'=>$category,
							'{$NAME_TEXT}'=>_T('post_name'), '{$CATEGORY_TEXT}'=>_T('post_category')
						)
					);
					echo $dat, '<input type="submit" value="'._T('del_btn').'" /></form></body></html>';
					return;
				} else if($_GET['action'] == 'delpush') { // delete
					$delno = array();
					reset($_POST);
					while ($item = each($_POST)) {
						if ($item[1]=='delete' && $item[0] != 'func')
							array_push($delno, $item[0]);
					}
					if (count($delno)) {
						foreach($delno as $d) {
							if(isset($pushpost[$d-1])) unset($pushpost[$d-1]);
						}
					}
					$pushcount = count($pushpost);
					if ($pushcount) {
						$f->update('mppCnt', $pushcount); // 更新推文次數
						$com = $ocom.$this->PUSHPOST_SEPARATOR.'<br />'.implode('<br />', $pushpost);
					} else {
						$f->remove('mppCnt'); // 刪除推文次數
						$com = $ocom;
					}

					$PIO->updatePost($_GET['no'], array('com' => $com, 'status' => $f->toString())); // 更新推文
					$PIO->dbCommit();

					header('HTTP/1.1 302 Moved Temporarily');
					header('Location: '.fullURL().PHP_SELF.'?page_num=0');
					return;
				} else die('[Error] unknown action.');
		}
		// 非 AJAX 推文，產出表單供填寫
		if (!isset($_POST['comm'])) {
			echo $this->printStaticForm(intval($_GET['no']));
		} else {
		// 處理推文
			// 傳送方法不正確
			if ($_SERVER['REQUEST_METHOD'] != 'POST') {
				die(_T('regist_notpost'));
			}

			// 查IP
			$baninfo = '';
			$ip = getREMOTE_ADDR();
			$host = gethostbyaddr($ip);
			if (BanIPHostDNSBLCheck($ip, $host, $baninfo)) {
				die(_T('regist_ipfiltered', $baninfo));
			}

			$name = CleanStr($_POST['name']);
			$comm = CleanStr($_POST['comm']);
			if (strlen($name) > 30) die($this->_T('maxlength')); // 名稱太長
			if (strlen($comm) > 160) die($this->_T('maxlength')); // 太多字
			if (strlen($comm) == 0) die($this->_T('nocomment')); // 沒打字
			$name = str_replace(
				array(_T('trip_pre'), _T('admin'), _T('deletor')),
				array(_T('trip_pre_fake'), '"'._T('admin').'"', '"'._T('deletor').'"'),
				$name
			);
			// 生成ID, Trip 等識別資訊
			$pushtime = gmdate('y/m/d H:i', time() + intval(TIME_ZONE) * 3600);
			if (preg_match('/(.*?)[#＃](.*)/u', $name, $regs)) {
				$cap = strtr($regs[2], array('&amp;'=>'&'));
				$salt = strtr(preg_replace('/[^\.-z]/', '.', substr($cap.'H.', 1, 2)), ':;<=>?@[\\]^_`', 'ABCDEFGabcdef');
				$name = $regs[1]._T('trip_pre').substr(crypt($cap, $salt), -10);
			}
			if (!$name || preg_match("/^[ |　|]*$/", $name)) {
				if (ALLOW_NONAME) $name = DEFAULT_NONAME;
				else die(_T('regist_withoutname')); // 不接受匿名
			}
			if (ALLOW_NONAME == 2) { // 強制砍名
				$name = preg_match('/(\\'._T('trip_pre').'.{10})/', $name, $matches) ? $matches[1].':' : DEFAULT_NONAME.':';
			} else {
				$name .= ':';
			}
			$pushpost = "{$name} {$comm} ({$pushtime})"; // 推文主體

			$post = $PIO->fetchPosts($_GET['no']);
			if (!count($post)) die('[Error] Post does not exist.'); // 被推之文章不存在

			$parentNo = $post[0]['resto'] ? $post[0]['resto'] : $post[0]['no'];
			$threads = array_flip($PIO->fetchThreadList());
			$threadPage = floor($threads[$parentNo] / PAGE_DEF);

			$p = ($parentNo==$post[0]['no']) ? $post : $PIO->fetchPosts($parentNo); // 取出首篇
			$flgh = $PIO->getPostStatus($p[0]['status']);
			if ($flgh->exists('TS')) die('[Error] '._T('regist_threadlocked')); // 首篇禁止回應/同時表示禁止推文

			$post[0]['com'] .= ((strpos($post[0]['com'], $this->PUSHPOST_SEPARATOR.'<br />')===false) ? '<br />'.$this->PUSHPOST_SEPARATOR : '').'<br /> '.$pushpost;
			$flgh2 = $PIO->getPostStatus($post[0]['status']);
			$flgh2->plus('mppCnt'); // 推文次數+1
			$PIO->updatePost($_GET['no'], array('com'=>$post[0]['com'], 'status'=>$flgh2->toString())); // 更新推文
			$PIO->dbCommit();

			// mod_audit logcat
			$this->callCHP('mod_audit_logcat',
				array(sprintf('[%s] No.%d %s (%s)',
					__CLASS__,
					$_GET['no'],
					$comm)
				)
			);

			if (STATIC_HTML_UNTIL == -1 || $threadPage <= STATIC_HTML_UNTIL) {
				// 僅更新討論串出現那頁
				updatelog(0, $threadPage, true);
			}
			deleteCache(array($parentNo)); // 刪除討論串舊快取

			if (isset($_POST['ajaxmode'])) {
				echo '+OK ', $pushpost;
			} else {
				header('HTTP/1.1 302 Moved Temporarily');
				header('Location: '.fullURL().PHP_SELF2.'?'.time());
			}
		}
	}

	/**
	 * 產出靜態推文表單
	 *
	 * @param  int $targetPost 推文對象文章編號
	 * @return string             表單頁面 HTML
	 */
	private function printStaticForm($targetPost) {
		$PIO = PMCLibrary::getPIOInstance();
		$PTE = PMCLibrary::getPTEInstance();

		$post = $PIO->fetchPosts($targetPost);
		if (!count($post)) die('[Error] Post does not exist.');

		$dat = $PTE->ParseBlock('HEADER', array('{$TITLE}'=>TITLE, '{$RESTO}'=>''));
		$dat .= '</head><body id="main">';
		$dat .= '<form action="'.$this->getModulePageURL(array('no' => $targetPost)).'" method="post">
'.$this->_T('pushpost').' <ul><li>'._T('form_name').' <input type="text" name="name" maxlength="20" /></li><li>'._T('form_comment').' <input type="text" name="comm" size="50" maxlength="50" /><input type="submit" value="'._T('form_submit_btn').'" /></li></ul>
</form>';
		$dat .= '</body></html>';
		return $dat;
	}

	/**
	 * 動態加入語言資源
	 */
	private function loadLanguage() {
		$lang = array(
			'zh_TW' => array(
				'nocomment' => '請輸入內文',
				'pushpost' => '[推文]',
				'pushbutton' => '推',
				'maxlength' => '你話太多了',
				'omitted' => '有部分推文被省略。要閱讀全部推文請按下回應連結。',
				'deletepush' => '刪除推文模式'
			),
			'ja_JP' => array(
				'nocomment' => '何か書いて下さい',
				'pushpost' => '[推文]',
				'pushbutton' => '推',
				'maxlength' => 'コメントが長すぎます',
				'omitted' => '推文省略。全て読むには返信ボタンを押してください。',
				'deletepush' => '削除推文モード'
			),
			'en_US' => array(
				'nocomment' => 'Please type your comment.',
				'pushpost' => '[Push this post]',
				'pushbutton' => 'PUSH',
				'maxlength' => 'You typed too many words',
				'omitted' => 'Some pushs omitted. Click Reply to view.',
				'deletepush' => 'Delete Push Post Mode'
			)
		);
		$this->attachLanguage($lang, 'en_US');
	}
}
