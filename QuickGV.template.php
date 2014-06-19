<?php
/*************************************************
 * Template of graph description (dot syntax)
 *************************************************/

// theme definitions
$THEME_ATTRS = array(
	'cold' => array(
		'graph_background' => '#000066',
		'edge_path'   => '#ffffff',
		'edge_font'   => '#ffffff',
		'node_border' => '#ffffff',
		'node_font'   => '#000000',
		'node_fill'   => '#ccffff:#00c0ff'
	),
	'warm' => array(
		'graph_background' => '#fffff7',
		'edge_path'   => '#704000',
		'edge_font'   => '#704000',
		'node_border' => '#c07000',
		'node_font'   => '#000000',
		'node_fill'   => '#ffffff:#ffffc0'
	),
	'sakura' => array(
		'graph_background' => '#996677',
		'edge_path'   => '#ffffff',
		'edge_font'   => '#ffffff',
		'node_border' => '#cc4444',
		'node_font'   => '#000000',
		'node_fill'   => '#ffffff:#ffc0d0'
	),
	'default' => array(
		'graph_background' => '#f0f0f0:#ffffff',
		'edge_path'   => '#555555',
		'edge_font'   => '#000000',
		'node_border' => '#aaaaaa',
		'node_font'   => '#000000',
		'node_fill'   => '#ffffff:#e7e7e7'
	),
);

/**
 * Get shell argument. If not exists, use default instead.
 *
 * @since 0.2.0
 */
function shell_arg($rank, $default_value='') {
	global $argv;

	if (isset($argv[$rank])) {
		$v = trim($argv[$rank]);
		if ($v!=='') return $v;
	}

	return $default_value;
}

// get graph name & theme name
$gname = shell_arg(1, 'G');
$theme = shell_arg(2, 'default');
$usage = shell_arg(3);

// replace aliases of usage
if ($usage=='er') $usage = 'record';
if ($usage=='mindmap') $usage = 'neato';

// get graph description
$gdata = trim(file_get_contents('php://stdin'));

if (!isset($THEME_ATTRS[$theme])) $theme = 'default';
$attrs =& $THEME_ATTRS[$theme];
?>
digraph <?php echo $gname; ?> {

	// options
	// theme = <?php echo "$theme\n"; ?>
	// usage = <?php echo "$usage\n"; ?>

	// default settings of graphs
	graph [
		rankdir = LR,
		bgcolor = "<?php echo $attrs['graph_background']; ?>",
		gradientangle = 65,
		<?php if ($usage=='neato'): ?>
		layout = neato,
		start  = "A",
		<?php endif; ?>
	];

	// default settings of nodes
	node [
		<?php if ($usage==='record'): ?>
		shape = record,
		style = filled,
		<?php else: ?>
		shape = box,
		style = "filled,rounded",
		<?php endif; ?>

		height   = 0.3,
		fontsize = 10,
		
		// theme
		color     = "<?php echo $attrs['node_border']; ?>",
		fontcolor = "<?php echo $attrs['node_font']; ?>",
		fillcolor = "<?php echo $attrs['node_fill']; ?>",
		gradientangle = 295 // left, top -> right, bottom
	];

	// default settings of edges
	edge [
		color     = "<?php echo $attrs['edge_path']; ?>",
		fontcolor = "<?php echo $attrs['edge_font']; ?>",
		fontsize  = 10
	];

	<?php if ($gdata==''): ?>

	// default graph
	A [label="if (Z>B)"];
	B [label="Banana"];
	C [label="Sunflower"];
	A -> B [label="yes"];
	A -> C [label="no"];

	<?php else: ?>

	// nodes, edges, and clusters
	<?php echo $gdata; ?>

	<?php endif; ?>

}