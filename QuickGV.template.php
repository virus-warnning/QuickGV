<?php
// 取得 graph 名稱 
$gname = isset($argv[1]) ? trim($argv[1]) : 'G';
if ($gname=='') $gname = 'G';

// 取得 graph 資料
$gdata = trim(file_get_contents('php://stdin'));
if ($gdata=='') $gdata = 'A -> {B C};';

// 取得 graph 用途
// TODO: ...
?>
digraph <?php echo $gname; ?> {

	// defaults of graphs
	graph [
		rankdir = LR
	];

	// defaults of nodes
	node [
		shape = box,
		style = "filled,rounded",
		fontsize = 10,
		height = 0.3,
		fontcolor = "#000000",
		color = "#c07000",
		fillcolor = "white:#ffffc0",
		gradientangle = 285
	];

	// default of edges
	edge [
		color="#444444",
		fontcolor="#444444",
		fontsize=10
	];

	// nodes, edges, and clusters
	<?php echo $gdata; ?>

}
