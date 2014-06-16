<?php
/*************************************************
 * Template of graph description (dot syntax)
 *************************************************/

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

// theme definitions
// TODO: move theme attributes to a separate PHP file
$theme_attrs = array(
	'blue' => array(
		'node_border' => '#0070c0',
		'node_font'   => '#000000',
		'node_fill'   => '#ffffff:#00c0ff'
	),
	'default' => array(
		'node_border' => '#c07000',
		'node_font'   => '#000000',
		'node_fill'   => '#ffffff:#ffffc0'
	),
);

if (!isset($theme_attrs[$theme])) $theme = 'default';
?>
digraph <?php echo $gname; ?> {

	// options
	// theme = <?php echo "$theme\n"; ?>
	// usage = <?php echo "$usage\n"; ?>

	// default settings of graphs
	graph [
		rankdir = LR,
		<?php if ($usage=='neato'): ?>
		layout = neato;
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
		color     = "<?php echo $theme_attrs[$theme]['node_border']; ?>",
		fontcolor = "<?php echo $theme_attrs[$theme]['node_font']; ?>",
		fillcolor = "<?php echo $theme_attrs[$theme]['node_fill']; ?>",
		gradientangle = 285
	];

	// default settings of edges
	edge [
		color     = "#444444", // line
		fontcolor = "#444444", // text
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