<!DOCTYPE html>
<html lang="en">
<head>
  <title>HeatpumpMonitor.org</title>
  <link rel="stylesheet" href="theme/style.css?v=8" />
  <link href="https://openenergymonitor.org/homepage/theme/favicon.ico" rel="shortcut icon">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/fontawesome.min.css">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/solid.min.css">
</head>

<body>

<?php
$navigation = array(
  array("controller"=>"", "href"=>".", "title"=>"Home", "icon"=>"fa-home"),
  array("controller"=>"stats", "href"=>"stats", "title"=>"30 Day Stats", "icon"=>"fa-table"),
  array("controller"=>"costs", "href"=>"costs", "title"=>"Running Costs", "icon"=>"fa-coins"),
  array("controller"=>"graph", "href"=>"graph", "title"=>"Comparison Charts", "icon"=>"fa-chart-line"),
  array("controller"=>"compare", "href"=>"compare", "title"=>"Comparison Charts", "icon"=>"fa-object-group")
);
?>

<div id="header">
  <div id="title"><span class="big">
    <b>HeatpumpMonitor</b>.org</span></div>
  <div id="navigation">
    <?php foreach ($navigation as $nav) { 
      $active = "";
      if ($route->controller==$nav['controller']) $active = 'class="active"'; 
    ?>
    <a href="<?php echo $nav['href']; ?>" title="<?php echo $nav['title']; ?>" <?php echo $active; ?>><i aria-hidden="true" class="fas <?php echo $nav['icon']; ?>"></i></a>
    <?php } ?>
  </div>
  <div id="tagline">An open source initiative to share and compare heat pump performance data.</div>
</div>

<?php echo $content; ?>

<div class="footer">An <a href="https://openenergymonitor.org/">OpenEnergyMonitor.org</a> community initiative</div>
</body>
</html>
