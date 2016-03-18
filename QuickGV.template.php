<?php
/*************************************************
 * Template of graph description (dot syntax)
 *************************************************/

// theme definitions
$THEME_ATTRS = array(
	'cold' => array(
		'graph_bg'     => '#555566',
		'graph_label'  => '#ffffff',
		'graph_border' => '#ffffff',
		'edge_path'    => '#ffffff',
		'edge_font'    => '#ffffff',
		'node_border'  => '#ffffff',
		'node_font'    => '#000000',
		'node_fill'    => '#ccffff:#00c0ff'
	),
	'warm' => array(
		'graph_bg'     => '#fffff7',
		'graph_label'  => '#000000',
		'graph_border' => '#804000',
		'edge_path'    => '#704000',
		'edge_font'    => '#704000',
		'node_border'  => '#c07000',
		'node_font'    => '#000000',
		'node_fill'    => '#ffffff:#ffffc0'
	),
	'sakura' => array(
		'graph_bg'     => '#996677',
		'graph_label'  => '#ffffff',
		'graph_border' => '#ffffff',
		'edge_path'    => '#ffffff',
		'edge_font'    => '#ffffff',
		'node_border'  => '#cc4444',
		'node_font'    => '#000000',
		'node_fill'    => '#ffffff:#ffc0d0'
	),
	'default' => array(
		'graph_bg'     => '#f0f0f0:#ffffff',
		'graph_label'  => '#000000',
		'graph_border' => '#555555',
		'edge_path'    => '#555555',
		'edge_font'    => '#000000',
		'node_border'  => '#aaaaaa',
		'node_font'    => '#000000',
		'node_fill'    => '#ffffff:#e7e7e7'
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

// replace the alias of usage
if ($usage==='er' || $usage==='ram') $usage = 'record';
if ($usage==='mindmap') $usage = 'neato';

// get graph description
$gdata = trim(file_get_contents('php://stdin'));

if (!isset($THEME_ATTRS[$theme])) $theme = 'default';
if ($gdata==='') $theme = 'warm';
$attrs =& $THEME_ATTRS[$theme];
?>
digraph <?php echo $gname; ?> {

	// options
	// theme = <?php echo "$theme\n"; ?>
	// usage = <?php echo "$usage\n"; ?>

	// default settings of graphs
	graph [
		rankdir   = LR,
		color     = "<?php echo $attrs['graph_border']; ?>",
		bgcolor   = "<?php echo $attrs['graph_bg']; ?>",
		fontcolor = "<?php echo $attrs['graph_label']; ?>",
		fontsize  = 12,
		style     = dashed,
		gradientangle = 65,

		<?php if ($usage==''): ?>
		splines = ortho,
		<?php endif; ?>

		<?php if ($usage=='neato'): ?>
		splines = curved,
		layout  = neato,
		start   = "A",
		<?php endif; ?>

		<?php if ($usage==='record'): ?>
		// * ortho, curved are bad
		// * polyline acts as line
		// * spline (default) is ok
		splines = spline,
		<?php endif; ?>
	];

	// default settings of nodes
	node [
		<?php if ($usage==='record'): ?>
		shape = record,
		style = filled,
		labelloc = l,
		<?php else: ?>
		shape = box,
		style = "filled,rounded",
		<?php endif; ?>

		height    = 0.3,
		fontsize  = 10,
		
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
		fontsize  = 10,
		arrowsize = 0.6
	];

	<?php if ($gdata===''): ?>

	//--------------------------
	// default graph ---- begin
	//--------------------------

	graph [rankdir=TB];

	A  [label="Can it work?"];
	B  [label="Did you touch it?"];
	C  [label="Does anybody know that?"];
	Z1 [label="It's OK! Don't touch it."];
	Z2 [label="Oh! You are such a fool."];

	A -> Z1 [label="Yes"];
	A -> B  [label="No"];
	B -> Z1 [label="No"];
	B -> C  [label="Yes"];
	C -> Z1 [label="No"];
	C -> Z2 [label="Yes"];

	//--------------------------
	// default graph ---- end
	//--------------------------

	<?php else: ?>

	// nodes, edges, and clusters
	<?php echo $gdata; ?>

	<?php endif; ?>

}
