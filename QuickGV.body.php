<?php
/**
 * Graphviz 快速製圖器
 *
 * @since  0.1.0
 * @author Raymond Wu https://github.com/virus-warnning
 */
class QuickGV {

	/* 錯誤訊息暫存區 */
	private static $errmsgs = array();

	/* 輸入內容上限 (1M) */
	const MAX_INPUTSIZE = 1048576;

	/**
	 * 掛載點設定 (由 MediaWiki 觸發)
	 *
	 * @since 0.1.0
	 * @param $parser MediaWiki 的語法處理器
	 */
	public static function init(&$parser) {
		$parser->setHook('quickgv', array('QuickGV', 'render'));
		return true;
	}

	/**
	 * 製圖 (由 MediaWiki 觸發)
	 *
	 * @since 0.1.0
	 * @param $in     MediaWiki 寫的語法內文
	 * @param $param  標籤內的參數
	 * @param $parser MediaWiki 的語法處理器
	 * @param $frame  不知道是啥小
	 */
	public static function render($in, $param=array(), $parser=null, $frame=false) {
		global $IP, $wgScriptPath;

		// 計時開始，效能計算用
		$beg_time = microtime(true);

		// 參數檢查
		self::validateParam($param);
		if (count(self::$errmsgs)>0) {
			return self::showError();
		}

		// 環境檢查
		$dotcmd = self::findDot();
		if ($dotcmd=='') {
			return self::showError();
		}

		// $in 上限管制
		if (strlen($in)>self::MAX_INPUTSIZE) {
			$msg = sprintf('Input data exceed %s.', self::getFriendlySize(self::MAX_INPUTSIZE));
			return self::showError($msg);
		}

		$gname    = self::getParam($param, 'name'    , 'G');
		$showmeta = self::getParam($param, 'showmeta', 'false');
		$showdot  = self::getParam($param, 'showdot' , 'false');

		$prefix = $parser->mTitle;
		$prefix = str_replace(array('\\','/',' '), '_', $prefix);

		$imgdir = sprintf('%s/images/quickgv', $IP);
		if (!is_dir($imgdir)) mkdir($imgdir);

		$fn = sprintf('%s-%s', $prefix, $gname);
		$infile  = sprintf('%s/images/quickgv/%s.in' , $IP, $fn);
		$dotfile = sprintf('%s/images/quickgv/%s.dot', $IP, $fn);
		$svgfile = sprintf('%s/images/quickgv/%s.svg', $IP, $fn);
		$svgurl  = sprintf('%s/images/quickgv/%s.svg', $wgScriptPath, $fn);

		// in 處理
		file_put_contents($infile, trim($in));

		// 產生 dot 語法
		$dotexec = __DIR__ . '/QuickGV.template.php';
		$cmd = sprintf(
			'php %s %s < %s > %s',
			escapeshellarg($dotexec), // $argv[0]
			escapeshellarg($gname),   // $argv[1]
			escapeshellarg($infile),  // stdin
			escapeshellarg($dotfile)  // stdout
		);
		system($cmd);
		unlink($infile);

		// 執行 dot，產生 svg 圖檔
		$errfile = tempnam($imgdir,'stderr-');
		$cmd = sprintf('%s -Tsvg %s > %s 2> %s',
			escapeshellarg($dotcmd),  // dot fullpath
			escapeshellarg($dotfile), // stdin
			escapeshellarg($svgfile), // stdout
			escapeshellarg($errfile)  // stderr
		);
		system($cmd, $status);

		// dot 指令的錯誤處理
		if ($status!=0) $errstr = file_get_contents($errfile);
		unlink($errfile);
		if ($status!=0) {
			$html = self::showError($errstr);
			$html .= sprintf('<pre>%s</pre>', file_get_contents($dotfile));
			return $html;
		}

		// 輸出
		$html = sprintf('<p><img src="%s?t=%d" style="border:1px solid #777;" /></p>', $svgurl, time());

		if ($showmeta==='true') {
			$elapsed = microtime(true) - $beg_time;

			// 取 Graphviz 版本資訊 (需要獨立 function)
			$verstr = system('dot -V 2>&1');
			$verpos = strpos($verstr,'version')+8;
			$verstr = substr($verstr,$verpos);

			// 取人性化的檔案大小
			$size = self::getFriendlySize(filesize($svgfile));

			$table_html = array();
			$table_html[] = sprintf('<tr><th>%s</th><td style="text-align:left;">%s</td></tr>', wfMessage('filepath'), $svgurl);
			$table_html[] = sprintf('<tr><th>%s</th><td style="text-align:left;">%s</td></tr>', wfMessage('filesize')->plain(), $size);
			$table_html[] = sprintf('<tr><th>%s</th><td style="text-align:left;">%.3f %s</td></tr>', wfMessage('exectime')->plain(), $elapsed, wfMessage('seconds')->plain());
			$table_html[] = sprintf('<tr><th>%s</th><td style="text-align:left;">%s</td></tr>', wfMessage('graphviz-path')->plain(), $dotcmd);
			$table_html[] = sprintf('<tr><th>%s</th><td style="text-align:left;">%s</td></tr>', wfMessage('graphviz-ver')->plain(), $verstr);
			$table_html[] = sprintf('<tr><th>%s</th><td style="text-align:left;"><a href="%s" target="_blank">%2$s</a></td></tr>', wfMessage('graphviz-ref')->plain(), 'http://www.graphviz.org/doc/info/attrs.html');
			$table_html = implode("\n", $table_html);
			$table_html = sprintf('<table class="mw_metadata" style="margin-left:0; margin-top:5px;"><tbody>%s</tbody></table>',$table_html);
			$html .= $table_html;
			unset($table_html);
		}

		if ($showdot==='true') {
			$html .= sprintf('<pre>%s</pre>', file_get_contents($dotfile));
		}

		// 移除暫時性的 dot
		unlink($dotfile);

		return $html;
	}

	/**
	 * 增加錯誤訊息
	 *
	 * @since 0.1.1
	 * @param $msg 錯誤訊息
	 */
	public static function addError($msg) {
		self::$errmsgs[] = $msg;
	}

	/**
	 * 顯示錯誤訊息
	 *
	 * @since 0.1.1
	 * @param $msg 錯誤訊息，如果沒有提供，會使用 addError 增加的錯誤訊息
	 */
	public static function showError($msg='') {
		// 內建 CSS:
		// .errorbox   - MW 顯示錯誤訊息的 CSS class
		// .warningbox - MW 顯示警示訊息的 CSS class
		// 這兩個都有 float: left; 用完後需要 clear 一下
		// 這些 CSS 真是夠醜的，以後要修一下

		if ($msg==='') {
			if (count(self::$errmsgs)>0) {
				foreach (self::$errmsgs as $cached_msg) {
					$html .= "<p>$cached_msg</p>";
				}
			} else {
				$html = "<p>Test</p>";
			}
		} else {
			$html = "<p>$msg</p>";
		}

		$html = sprintf('<div class="errorbox" style="margin:0;">%s</div>',$html);
		$html .= '<div style="clear:both;"></div>';
		return $html;
	}

	/**
	 * 檢查參數
	 *
	 * @param $params 設定值組
	 */
	public static function validateParam(&$params) {
		$patterns = array(
			'bool' => '/^(true|false)$/',
			'name' => '/^[a-zA-Z0-9_]+$/',
		);

		$descs = array(
			'bool' => 'true or false',
			'name' => 'these characters a~z, A~Z or 0~9',
		);

		$formats = array(
			'name' => 'name',
			'showdot' => 'bool',
			'showmeta' => 'bool',
		);

		foreach ($formats as $prmk => $patk) {
			if (isset($params[$prmk])) {
				$param = $params[$prmk];
				$pattern = $patterns[$patk];
				if (!preg_match($pattern,$param)) {
					// TODO: 之後需要翻譯一下
					$msg = sprintf('Attribute %s="%s" needs %s.', $prmk, $param, $descs[$patk]);
					self::addError($msg);
				}
			}
		}
	}

	/**
	 * 檢查 dot 指令是否存在
	 *
	 * @return dot 指令的完整路徑 (目前不進行 realpath 處理)
	 */
	public static function findDot() {
		$dotpath = exec('which dot'); // if not found, return string(0) ""
		$dotpath = '';
		if ($dotpath==='') {
			$guesslist = array(
				'/usr/bin/dot',
				'/usr/local/bin/dot'
			);
			foreach ($guesslist as $guessitem) {
				if (file_exists($guessitem)) {
					$dotpath = $guessitem;
					break;
				}
			}
		}

		if ($dotpath==='') {
			self::addError('Graphviz is not installed or not found.');
			return '';
		}

		if (!is_executable($dotpath)) $dotpath = '';

		if ($dotpath==='') {
			self::addError('Graphviz is not executable.');
			return '';
		}

		return $dotpath;
	}

	/**
	 * 取得設定值，如果沒提供就使用預設值
	 *
	 * @param  $params  設定值組
	 * @param  $key     設定值名稱
	 * @param  $default 預設值
	 * @return 預期結果
	 */
	public static function getParam(&$params, $key, $default='') {
		if (isset($params[$key])) {
			if (trim($params[$key])!=='') return $params[$key];
		}
		return $default;
	}

	/**
	 * 取得人性化的檔案大小
	 *
	 * @param $size 位元組數
	 */
	public static function getFriendlySize($size) {
		static $unit_ch = array('B','KB','MB');

		$unit_lv = 0;
		while ($size>=1024 && $unit_lv<=2) {
			$size /= 1024;
			$unit_lv++;
		}
		
		if ($unit_lv==0) {
			return sprintf('%d %s', $size, $unit_ch[$unit_lv]);
		} else {
			return sprintf('%.2f %s', $size, $unit_ch[$unit_lv]);
		}
	}

}
