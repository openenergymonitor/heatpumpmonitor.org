<?php
// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

global $path, $settings, $session;

$navigation = array(
    array("controller" => "", "href" => ".", "title" => "Home", "icon" => "fa-home"),
    // array("controller" => "stats", "href" => "stats", "title" => "30 Day Stats", "icon" => "fa-table"),
    // array("controller" => "costs", "href" => "costs", "title" => "Running Costs", "icon" => "fa-coins"),
    // array("controller" => "graph", "href" => "graph", "title" => "Comparison Charts", "icon" => "fa-chart-line"),
    // array("controller" => "compare", "href" => "compare", "title" => "Comparison Charts", "icon" => "fa-object-group"),
    array("controller" => "user", "href" => "user/login", "title" => "Login", "icon" => "fa-user")
);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1">
    <title>HeatpumpMonitor.org</title>
    <meta name="description" content="An open-source initiative to share and compare heat pump performance data. Join our community of heat pump owners sharing real-world performance data.">
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://heatpumpmonitor.org/">
    <meta property="og:title" content="HeatpumpMonitor.org - Heat Pump Performance Data">
    <meta property="og:description" content="An open-source initiative to share and compare heat pump performance data. Join our community of heat pump owners sharing real-world performance data.">
    <meta property="og:image" content="https://heatpumpmonitor.org/heatpumpmonitor.png">
    
    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="https://heatpumpmonitor.org/">
    <meta property="twitter:title" content="HeatpumpMonitor.org - Heat Pump Performance Data">
    <meta property="twitter:description" content="An open-source initiative to share and compare heat pump performance data. Join our community of heat pump owners sharing real-world performance data.">
    <meta property="twitter:image" content="https://heatpumpmonitor.org/heatpumpmonitor.png">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link href="https://openenergymonitor.org/homepage/theme/favicon.ico" rel="shortcut icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/fontawesome.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/solid.min.css">
    <link rel="stylesheet" href="<?php echo $path; ?>theme/style.css?v=54" />

</head>

<style>
    .bg-custom {
        background-color: #44b3e2;
        /* Replace with your custom color value */
    }

    .navbar-brand {
        font-size: 22px;
        /* Replace with your desired font size */
    }

    .navbar-nav .nav-link {
        font-size: 18px;
        /* Replace with your desired font size */
    }

    .navbar .navbar-nav .nav-link i {
        margin-right: 0.5rem;
        font-size: 25px;
    }

    .navbar-text-desktop {
        color: rgba(255, 255, 255, 0.8);
    }

    .footer {

        padding: 20px;
        text-align: center;
    }

    @media (min-width: 992px) {
        .nav-item-text {
            display: none;
        }
    }

    @media (max-width: 1200px) {
        .navbar-text-desktop {
            display: none;
        }
    }
</style>

<script>
    var path = "<?php echo $path; ?>";
    var public_mode_enabled = <?php echo (int) $settings['public_mode_enabled']; ?>;
</script>

<body class="d-flex flex-column min-vh-100">
    <header>
        <nav class="navbar navbar-expand-lg navbar-dark bg-custom">
            <div class="container-fluid">
                <a class="navbar-brand" href="<?php echo $path; ?>"><b>HeatpumpMonitor</b>.org</a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <span class="navbar-text navbar-text-desktop">An open-source initiative to share and compare heat pump performance data</span>
 
                    <?php if ($settings['public_mode_enabled'] || $session['userid']) { ?>

                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item"><a class="nav-link " href="<?php echo $path; ?>" title="Home"><i class="fas fa-home"></i> <span class="nav-item-text">Home</span></a></li>
                    </ul>

                    <ul class="navbar-nav">
                        <li class="nav-item"><a id="map-link" class="nav-link " href="<?php echo $path; ?>map" title="Map"><i class="fas fa-map"></i> <span class="nav-item-text">Map</span></a></li>
                    </ul>

                    <ul class="navbar-nav">
                        <li class="nav-item"><a class="nav-link " href="https://docs.openenergymonitor.org/heatpumpmonitor" title="Docs"><i class="fas fa-book"></i> <span class="nav-item-text">Docs</span></a></li>
                    </ul>

                    <ul class="navbar-nav"> 
                        <li class="nav-item"><a id="heatpump-database-link" class="nav-link " href="<?php echo $path; ?>heatpump" title="Heat pump database"><i class="fas fa-database"></i> <span class="nav-item-text">Heat pump database</span></a></li>
                    </ul>

                    <ul class="navbar-nav"> 
                        <li class="nav-item">
                            <a id="installer-link" class="nav-link" href="<?php echo $path; ?>installer" title="Installer database">
                                <i class="fas fa-tools"></i> <span class="nav-item-text">Installers</span>
                            </a>
                        </li>
                    </ul>

                    <ul class="navbar-nav">
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="avatarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-chart-line"></i>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="avatarDropdown">
                                <li><a class="dropdown-item" href="<?php echo $path; ?>heatloss">Heat demand tool</a></li>
                                <li><a class="dropdown-item" href="<?php echo $path; ?>daily">Daily</a></li>
                                <li><a class="dropdown-item" href="<?php echo $path; ?>monthly">Monthly</a></li>
                                <li><a class="dropdown-item" href="<?php echo $path; ?>compare">Compare</a></li>
                                <li><a class="dropdown-item" href="<?php echo $path; ?>histogram">Histogram</a></li>

                            </ul>
                        </li>
                    </ul>
                    <?php } ?>

                    <?php if (!$session['userid']) { ?>
                        <ul class="navbar-nav <?php if (!$settings['public_mode_enabled']) echo "ms-auto"; ?>">
                            <li class="nav-item"><a class="nav-link " href="<?php echo $path; ?>user/login" title="Login"><i class="fas fa-user"></i> <span class="nav-item-text">Login</span></a></li>
                        </ul>
                    <?php } else { ?>
                        <ul class="navbar-nav">
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" id="avatarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <img width="32" height="32" class="rounded-circle avatar-image">
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="avatarDropdown">
                                    <li><a class="dropdown-item" href="<?php echo $path; ?>user/view">My account</a></li>
                                    <li><a class="dropdown-item" href="<?php echo $path; ?>system/list/user">My systems</a></li>
                                    <?php if ($session['admin']) { ?>
                                        <li>
                                            <hr class="dropdown-divider">
                                        </li>
                                        <li><a class="dropdown-item" href="<?php echo $path; ?>user/admin">Admin users</a></li>
                                        <li><a class="dropdown-item" href="<?php echo $path; ?>system/list/admin">Admin systems</a></li>
                                        <li><a class="dropdown-item" href="<?php echo $path; ?>system/log">Change log</a></li>
                                        <li><a class="dropdown-item" href="<?php echo $path; ?>system/photos/admin">System photos</a></li>
                                    <?php } ?>
                                    <li>
                                        <hr class="dropdown-divider">
                                    </li>
                                    <li><a class="dropdown-item" href="<?php echo $path; ?>user/logout">Logout</a></li>
                                </ul>
                            </li>
                        </ul>
                    <?php } ?>
                </div>
            </div>
        </nav>
    </header>

    <div class="flex-grow-1">
        <?php echo $content; ?>
    </div>

    <footer class="footer sticky-footer bg-custom text-light">
        <div class="container">
            <div>An <a href="https://openenergymonitor.org/" class="oslink"><b>OpenEnergyMonitor.org</b></a> community initiative</div>
            <a href="https://github.com/openenergymonitor/heatpumpmonitor.org" class="oslink" style="font-size:14px">This website is open source on github</a>
            <p><a href="<?php echo $path; ?>about" class="oslink">About</a> | <a href="<?php echo $path; ?>api-helper" class="oslink">API</a></p>
        </div>
    </footer>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <?php if ($session['userid']) { ?>
        <script src="<?php echo $path; ?>Lib/md5.js"></script>
        <script>
            // Include gravitar profile image
            var avatar = document.getElementsByClassName("avatar-image");
            avatar[0].src = "https://www.gravatar.com/avatar/" + CryptoJS.MD5("<?php echo $session['email']; ?>") + "?s=32&d=mm";
        </script>
    <?php } ?>
</body>

</html>
