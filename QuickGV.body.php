<?php
/**
 * Graphviz 快速製圖
 */
class QuickGV {

	/**
	 * 設定函數鉤 (由 MediaWiki 觸發)
	 */
	public static function init(&$parser) {
		$parser->setHook('quickgv', array('QuickGV', 'render'));
		return true;
	}

	/**
	 * 製圖起點 (由 MediaWiki 觸發)
	 *
	 * @param $in MediaWiki 寫的語法內文
	 * @param $param 參數
	 */
	public static function render($in, $param=array(), $parser=null, $frame=false) {
		global $IP, $wgScriptPath;

		// 計時開始，效能計算用
		$beg_time = microtime(true);

		// TODO: 參數檢查

		// TODO: in 上限管制

		if (isset($param['name'])) $gname = trim($param['name']);
		if (!isset($gname) || $gname=='') $gname = 'G';

		if (isset($param['showmeta']) && $param['showmeta']==='true') {
			$showmeta = true;
		} else {
			$showmeta = false;
		}

		if (isset($param['showdot']) && $param['showdot']==='true') {
			$showdot = true;
		} else {
			$showdot = false;
		}

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

		// 產生 svg 圖檔
		$cmd = sprintf('dot -Tsvg %s > %s',
			escapeshellarg($dotfile),
			escapeshellarg($svgfile)
		);
		system($cmd);

		// TODO: dot 錯誤處理

		// 輸出
		$html = sprintf('<p><img src="%s" style="border:1px solid #777;" /></p>', $svgurl);

		if ($showmeta) {
			$elapsed = microtime(true) - $beg_time;

			$verstr = system('dot -V 2>&1');
			$verpos = strpos($verstr,'version');
			$verstr = substr($verstr,$verpos);

			$unit_lv = 0;
			$size = filesize($svgfile);
			while ($size>=1024 && $unit_lv<=2) {
				$size /= 1024;
				$unit_lv++;
			}
			$unit_ch = array('B','KB','MB');

			$html .= sprintf('<p>檔案路徑: %s</p>', $svgurl);
			$html .= sprintf('<p>檔案大小: %.2f %s</p>', $size, $unit_ch[$unit_lv]);
			$html .= sprintf('<p>轉檔時間: %.3f 秒</p>', $elapsed);
			$html .= sprintf('<p>Graphviz 版本: %s</p>', $verstr);
		}

		if ($showdot) {
			$html .= sprintf('<pre>%s</pre>', file_get_contents($dotfile));
		}

		// 移除暫時性的 dot
		unlink($dotfile);

		return $html;
	}

	/**
	 * 檢查參數正確性
	 */
	public static function validateParam() {

	}

	/**
	 * 檢查環境正確性
	 */
	public static function validateRequirements() {

	}

	/**
	 * 製圖
	 */
	public static function buildGraph() {

	}

}
