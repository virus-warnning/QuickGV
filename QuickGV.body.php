<?php
/**
 * Graphviz 快速製圖器
 *
 * @author Raymond Wu https://github.com/virus-warnning
 */
class QuickGV {

	/* 輸入內容上限 (1M) */
	const MAX_INPUTSIZE = 1048576;

	/* 自定義 dot 路徑 */
	const DOT_PATH = '';

	/* 自定義 php 路徑 */
	const PHP_PATH = '';
	//const PHP_PATH = 'C:\wamp\bin\php\php5.4.3\php.exe';

	/* 錯誤訊息暫存區 */
	private static $errmsgs = array();

	/* 版本字串 */
	private static $version;

	/**
	 * 掛載點設定 (由 MediaWiki 觸發)
	 *
	 * @since 0.1.0
	 * @param $parser MediaWiki 的語法處理器
	 */
	public static function init(&$parser) {
		// 取得版本字串
		global $wgExtensionCredits;
		foreach ($wgExtensionCredits['parserhook'] as $ext) {
			if ($ext['name']==='QuickGV') {
				self::$version = $ext['version'];
				break;
			}
		}

		// 設定函數鉤
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

		// dot 環境檢查
		$dotcmd = self::findExecutable('dot', self::DOT_PATH);
		if ($dotcmd=='') return self::showError();

		// PHP 環境檢查
		$phpcmd = self::findExecutable('php', self::PHP_PATH);
		if ($phpcmd=='') return self::showError();

		// $in 上限管制
		if (strlen($in)>self::MAX_INPUTSIZE) {
			$msg = sprintf('Input data exceed %s.', self::getFriendlySize(self::MAX_INPUTSIZE));
			return self::showError($msg);
		}

		// 計算新的摘要，快取處理用
		$sum_curr = md5(json_encode($param).$in);

		// 讀取參數，或是預設值
		$gname    = self::getParam($param, 'name'    , 'G');
		$theme    = self::getParam($param, 'theme'   , '');
		$usage    = self::getParam($param, 'usage'   , '');
		$showmeta = self::getParam($param, 'showmeta', 'false');
		$showdot  = self::getParam($param, 'showdot' , 'false');
		//return '<pre>' . print_r($param, true) . '</pre>';

		$prefix = $parser->mTitle;
		$prefix = str_replace(array('\\','/',' '), '_', $prefix); // TODO: 搬去 self::getSafeName()

		$imgdir = sprintf('%s/images/quickgv', $IP);
		if (!is_dir($imgdir)) mkdir($imgdir);

		$fn = self::getSafeName(sprintf('%s-%s', $prefix, $gname));
		$metafile = sprintf('%s/images/quickgv/%s-meta.json', $IP, $fn);
		$svgfile  = sprintf('%s/images/quickgv/%s.svg', $IP, $fn);
		$svgurl   = sprintf('%s/images/quickgv/%s.svg', $wgScriptPath, $fn);

		// 更新狀況檢查
		$modified = true;
		if (is_file($metafile) && is_file($svgfile)) {
			$meta = json_decode(file_get_contents($metafile),true);
			$sum_prev = $meta['md5sum'];
			if ($sum_curr==$sum_prev) {
				$modified = false;
				$elapsed  = $meta['elapsed'];
				$dotcode  = $meta['dotcode'];
			}
		}

		// 有更新才轉檔
		if ($modified) {
			// 執行 php, 產生 dot 語法
			$dottpl = __DIR__ . '/QuickGV.template.php';
			$cmd = sprintf(
				'%s %s %s %s %s',
				escapeshellarg($phpcmd), // php
				escapeshellarg($dottpl), // $argv[0]
				escapeshellarg($gname),  // $argv[1]
				escapeshellarg($theme),  // $argv[2]
				escapeshellarg($usage)   // $argv[3]
			);
			$retval = self::pipeExec($cmd, $in, $dotcode, $err, 'utf-8');

			// 執行 dot, 產生 svg 圖檔
			$cmd = sprintf('%s -Tsvg > %s',
				escapeshellarg($dotcmd),  // dot fullpath
				escapeshellarg($svgfile) // stdout
			);
			$retval = self::pipeExec($cmd, $dotcode, $out, $err, 'utf-8');

			// dot 指令的錯誤處理
			if ($retval!=0) {
				$html = self::showError($err);
				$html .= sprintf('<pre>%s</pre>', $dotcode);
				return $html;
			}

			// 如果輸出成功，記錄 "轉圖時間"、"MD5"
			// 如果有開啟顯示原始碼，也記錄 "dot 原始碼"
			$elapsed = microtime(true) - $beg_time;
			$meta = array(
				'md5sum'  => $sum_curr,
				'elapsed' => $elapsed,
				'dotcode' => ''
			);
			if ($showdot==='true') $meta['dotcode'] = $dotcode;
			file_put_contents($metafile, json_encode($meta));
		}

		// 輸出 (利用 mtime 讓圖片正確使用快取)
		$mtime = filemtime($svgfile);
		$html  = sprintf('<p><img src="%s?t=%d" style="border:1px solid #777;" /></p>', $svgurl, $mtime);

		// TODO: 之後實驗 SVG Link 用
		/*
		$svg_desc = file_get_contents($svgfile);
		$pos = strpos($svg_desc, '<svg');
		$svg_desc = substr($svg_desc, $pos);
		$html = sprintf('<pre>%s</pre>', htmlspecialchars($svg_desc));
		*/

		if ($showmeta==='true') {
			// 取 Graphviz 版本資訊 (需要獨立 function)
			$cmd = sprintf('%s -V', escapeshellarg($dotcmd));
			self::pipeExec($cmd, '', $out, $err);
			$verstr = trim($err);
			$verpos = strpos($verstr,'version')+8;
			$verstr = substr($verstr,$verpos);

			// 取人性化的檔案大小
			$size = self::getFriendlySize(filesize($svgfile));

			$table_html = array();
			$table_html[] = sprintf('<tr><th style="white-space:nowrap;">%s</th><td style="text-align:left;">%s</td></tr>', wfMessage('filepath'), $svgurl);
			$table_html[] = sprintf('<tr><th style="white-space:nowrap;">%s</th><td style="text-align:left;">%s</td></tr>', wfMessage('filesize')->plain(), $size);
			$table_html[] = sprintf('<tr><th style="white-space:nowrap;">%s</th><td style="text-align:left;">%s</td></tr>', wfMessage('filemtime')->plain(), date('Y-m-d H:i:s',$mtime));
			$table_html[] = sprintf('<tr><th style="white-space:nowrap;">%s</th><td style="text-align:left;">%.3f %s</td></tr>', wfMessage('exectime')->plain(), $elapsed, wfMessage('seconds')->plain());
			$table_html[] = sprintf('<tr><th style="white-space:nowrap;">%s</th><td style="text-align:left;">%s</td></tr>', wfMessage('md5sum')->plain(), $sum_curr);
			$table_html[] = sprintf('<tr><th style="white-space:nowrap;">%s</th><td style="text-align:left;">%s</td></tr>', wfMessage('graphviz-path')->plain(), $dotcmd);
			$table_html[] = sprintf('<tr><th style="white-space:nowrap;">%s</th><td style="text-align:left;">%s</td></tr>', wfMessage('graphviz-ver')->plain(), $verstr);
			$table_html[] = sprintf('<tr><th style="white-space:nowrap;">%s</th><td style="text-align:left;"><a href="%s" target="_blank">%2$s</a></td></tr>', wfMessage('graphviz-ref')->plain(), 'http://www.graphviz.org/doc/info/attrs.html');
			$table_html[] = sprintf('<tr><th style="white-space:nowrap;">%s</th><td style="text-align:left;">%s - <a href="https://www.mediawiki.org/wiki/Extension:QuickGV" target="_blank">%s</a></td></tr>', wfMessage('quickgv-ver')->plain(), self::$version, wfMessage('quickgv-about')->plain());
			$table_html = implode("\n", $table_html);
			$table_html = sprintf('<table class="mw_metadata" style="width:600px; margin:5px 0 0 0;"><tbody>%s</tbody></table>',$table_html);
			$html .= $table_html;
			unset($table_html);
		}

		if ($showdot==='true') {
			$html .= sprintf('<pre>%s</pre>', $dotcode);
		}

		return $html;
	}

	/**
	 * 增加錯誤訊息
	 *
	 * @since 0.1.1
	 * @param $msg 錯誤訊息
	 */
	private static function addError($msg) {
		self::$errmsgs[] = $msg;
	}

	/**
	 * 顯示錯誤訊息
	 *
	 * @since 0.1.1
	 * @param $msg 錯誤訊息，如果沒有提供，會使用 addError 增加的錯誤訊息
	 */
	private static function showError($msg='') {
		// 內建 CSS:
		// .errorbox   - MW 顯示錯誤訊息的 CSS class
		// .warningbox - MW 顯示警示訊息的 CSS class
		// 這兩個都有 float: left; 用完後需要 clear 一下
		// 這些 CSS 真是夠醜的，以後要修一下

		if ($msg==='') {
			if (count(self::$errmsgs)>0) {
				$html = '';
				foreach (self::$errmsgs as $cached_msg) {
					$html .= "<p>$cached_msg</p>";
				}

				// Clear messages, or graphs after this one will broken.
				self::$errmsgs = array();
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
	private static function validateParam(&$params) {
		// 正向表列格式清單
		$patterns = array(
			'bool' => '/^(true|false)$/',
			'name' => '/^[\w_]+$/u',      // 防止符號字元，而且支援中文
		);

		// 驗證失敗時的錯誤訊息
		$descs = array(
			'bool' => 'true or false',
			'name' => 'word characters or underscore',
		);

		// 驗證欄位與格式對應
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
	 * 搜尋程式的完整路徑
	 * 如果沒有自定義路徑，使用 which 或是 where 指令搜尋程式完整路徑，
	 * 如果有自定義路徑，則使用自定義路徑，不進行自動搜尋。
	 *
	 * @since  0.2.0
	 * @param  $exec_name   程式名稱
	 * @param  $exec_custom 自定義程式路徑
	 * @return 程式完整路徑
	 */
	private static function findExecutable($exec_name, $exec_custom) {
		if ($exec_custom==='') {
			if (PHP_OS!=='WINNT') {
				$exec_path = exec("which $exec_name");
				if ($exec_path==='') {
					$search_dirs = array(
						'/usr/bin',
						'/usr/local/bin'
					);
					foreach ($search_dirs as $dir) {
						$p = sprintf('%s/%s',$dir,$exec_name);
						if (file_exists($p)) {
							$exec_path = $p;
							break;
						}
					}
				}
			} else {
				// TODO 0.2.1: search dot.exe from:
				// * %ProgramFiles(x86)% - C:\Program Files (x86)
				// * %ProgramFiles%      - C:\Program Files
				// [Gg]raphviz\s?2\.\d+\bin\dot
				//$exec_path = exec("where $exec_name");

				$prog_files = getenv('ProgramFiles(x86)'); // for 64-bits Windows
				if ($prog_files===false) {
					$prog_files = getenv('ProgramFiles');  // for 32-bits Windows
				}

				$matched_dirs = array();
				$dh = opendir($prog_files);
				while (($prog_dir = readdir($dh))!==false) {
					if (preg_match('/[Gg]raphviz\s?(2\.\d+)/', $prog_dir, $matches)) {
						$gv_ver = (float)$matches[1];
						if ($gv_ver>=2.0) $matched_dirs[] = $prog_dir;
					}
				}
				closedir($dh);
				
				if (count($matched_dirs)) {
					rsort($matched_dirs);
					$prog_dir  = $matched_dirs[0];
					$exec_path = sprintf('%s\\%s\\bin\\dot.exe', $prog_files, $prog_dir);
				}
			}
		} else {
			$exec_path = $exec_custom;
		}

		if ($exec_path==='' || !file_exists($exec_path)) {
			if ($exec_name==='dot') $exec_name = 'Graphviz';
			self::addError("$exec_name is not installed.");

			// How to install graphviz
			$os = PHP_OS;
			switch ($os) {
				case 'Darwin':
					$url = 'http://brew.sh';
					self::addError('Run the command to install:');
					self::addError('<blockquote>brew install graphviz</blockquote>');
					self::addError(sprintf('If you didn\'t install Homebrew yet, see <a href="%1$s">%1$s</a>.', $url));
					break;
				case 'WINNT':
					$url = 'http://www.graphviz.org/Download_windows.php';
					self::addError(sprintf('Click here to download installer: <a href="%1$s">%1$s</a>', $url));
					break;
				case 'Linux':
					self::addError('For CentOS users, run the command to install:');
					self::addError('<blockquote>yum install graphviz</blockquote>');
					self::addError('For Ubuntu or Debian users, run the command to install:');
					self::addError('<blockquote>sudo apt-get install graphviz</blockquote>');
					break;
				case 'FreeBSD':
					self::addError('Run the command to install:');
					self::addError('<blockquote>pkg_add -r graphviz</blockquote>');
					break;
			}

			return '';
		}

		if (!is_executable($exec_path)) {
			self::addError("$exec_path is not executable.");
			return '';
		}

		return $exec_path;
	}

	/**
	 * 取得設定值，如果沒提供就使用預設值
	 *
	 * @param  $params  設定值組
	 * @param  $key     設定值名稱
	 * @param  $default 預設值
	 * @return 預期結果
	 */
	private static function getParam(&$params, $key, $default='') {
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
	private static function getFriendlySize($size) {
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

	/**
	 * 檔名迴避 Windows 不接受的字元
	 */
	private static function getSafeName($unsafename) {
		$safename = '';
		$slen = strlen($unsafename);

		// escape non-ascii chars
		for($i=0;$i<$slen;$i++) {
			$ch = $unsafename[$i];
			$cc = ord($ch);
			if ($cc<32 || $cc>127) {
				$safename .= sprintf('x%02x',$cc);
			} else {
				$safename .= $ch;
			}
		}

		return $safename;
	}

	/**
	 * shell 執行程式
	 *
	 * @since 0.2.0
	 */
	private static function pipeExec($cmd, $stdin='', &$stdout='', &$stderr='', $encoding='sys') {
		static $sys_encoding = '';

		if ($encoding==='sys') {
			// detect system encoding once
			if ($sys_encoding==='') {
				if (PHP_OS==='WINNT') {
					// for Windows
					$lastln = exec('chcp', $stdout, $retval);
					if ($retval===0) {
						$ok = preg_match('/: (\d+)$/', $lastln, $matches);
						if ($ok===1) $sys_encoding = sprintf('cp%d', (int)$matches[1]);
					}
				} else {
					// for Linux / OSX / BSD
					// TODO: ...
				}

				if ($sys_encoding==='') $sys_encoding = 'utf-8';
			}

			// apply system encoding
			$encoding = $sys_encoding;
		}

		// pipe all streams
		$desc = array(
			array('pipe', 'r'), // stdin
			array('pipe', 'w'), // stdout
			array('pipe', 'w')  // stderr
		);

		// run the command
		if (PHP_OS==='WINNT') $cmd = sprintf('"%s"', $cmd); // hack for windows
		$proc = proc_open($cmd, $desc, $pipes);
		if (is_resource($proc)) {
			$encoding = strtolower($encoding);

			// feed stdin
			if ($encoding!=='utf-8') {
				$stdin = iconv('utf-8', $encoding, $stdin);
			}
			fwrite($pipes[0], $stdin);
			fclose($pipes[0]);

			// read stdout
			$stdout = stream_get_contents($pipes[1]);
			if ($encoding!=='utf-8') {
				$stdout = iconv($encoding, 'utf-8', $stdout);
			}
			fclose($pipes[1]);

			// read stderr
			$stderr = stream_get_contents($pipes[2]);
			if ($encoding!=='utf-8') {
				$stderr = iconv($encoding, 'utf-8', $stderr);
			}
			fclose($pipes[2]);

			$retval = proc_close($proc);
		} else {
			$retval = -1;
		}

		return $retval;
	}

}
