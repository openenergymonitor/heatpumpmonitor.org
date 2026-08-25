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
    <meta name="theme-color" content="#44b3e2">
    
    <!-- Icons -->
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo $path; ?>theme/img/logo/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="<?php echo $path; ?>theme/img/logo/favicon-16x16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="<?php echo $path; ?>theme/img/logo/apple-touch-icon.png">
    <link rel="manifest" href="/site.webmanifest">
    <link rel="shortcut icon" href="<?php echo $path; ?>theme/img/logo/favicon.ico">

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
    
    <!-- PWA Manifest -->
    <link rel="manifest" href="<?php echo $path; ?>manifest.json">
    
    <!-- Icons -->
    <link rel="icon" type="image/x-icon" href="<?php echo $path; ?>theme/img/logo/favicon.ico">
    <link rel="apple-touch-icon" href="<?php echo $path; ?>theme/img/logo/apple-touch-icon.png">
    
    <link rel="stylesheet" href="<?php echo $path; ?>theme/vendor/bootstrap-5.3.0/css/bootstrap.min.css" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM">
    <link rel="stylesheet" href="<?php echo $path; ?>theme/vendor/bootstrap-icons-1.11.0/font/bootstrap-icons.css" integrity="sha384-QuGBSgV5Im3DzL2z+8Ko9/hqNy/N0O7zwvXAtfd1MvPKWa/UbeLV65cfm4BV5Wgq">
    <link rel="stylesheet" href="<?php echo $path; ?>theme/vendor/font-awesome-5.15.4/css/fontawesome.min.css" integrity="sha384-jLKHWM3JRmfMU0A5x5AkjWkw/EYfGUAGagvnfryNV3F9VqM98XiIH7VBGVoxVSc7">
    <link rel="stylesheet" href="<?php echo $path; ?>theme/vendor/font-awesome-5.15.4/css/solid.min.css" integrity="sha384-6/Qc8WwP5XndV6x+OXm6MNVF9o50ZZ/LpBmt7UlQp1pE5RACo7JZDbL9zglhDELh">
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
        display: flex;
        align-items: center;
    }

    .navbar-brand-logo {
        height: 36px;
        width: 36px;
        margin-right: 10px;
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

    .dropdown-item i {
        margin-right: 0.5rem;
        width: 1.25rem;
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
                <a class="navbar-brand" href="<?php echo $path; ?>system/list">
                    <img src="<?php echo $path; ?>theme/img/logo/apple-touch-icon.png" alt="HeatpumpMonitor Logo" class="navbar-brand-logo">
                    <span><b>HeatpumpMonitor</b>.org</span>
                </a>
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
                        <li class="nav-item"><a id="system-list-link" class="nav-link " href="<?php echo $path; ?>system/list" title="System list"><i class="fas fa-list-alt"></i> <span class="nav-item-text">System list</span></a></li>
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
                                <li><a class="dropdown-item" href="<?php echo $path; ?>aggregation">Aggregation</a></li>

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
                                <?php $show_gravatar = $session['email'] && gravatar_enabled(); ?>
                                <?php // s=52 rather than the 32 it is drawn at: that is the size emoncms.org
                                      // asks gravatar.com for, so the two sites share one cache entry per user ?>
                                <a class="nav-link dropdown-toggle" href="#" id="avatarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <?php if ($show_gravatar) { ?>
                                        <img width="32" height="32" class="rounded-circle avatar-image" alt="" src="<?php echo $path; ?>user/gravatar?hash=<?php echo hash('sha256', strtolower(trim($session['email']))); ?>&amp;s=52">
                                    <?php } else { ?>
                                        <i class="bi bi-person-circle" style="font-size:32px; line-height:1"></i>
                                    <?php } ?>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="avatarDropdown">
                                    <li><a class="dropdown-item" href="<?php echo $path; ?>user/account"><i class="bi bi-person-circle"></i> My account</a></li>
                                    <li><a class="dropdown-item" href="<?php echo $path; ?>system/list/user"><i class="bi bi-list-ul"></i> My systems</a></li>
                                    <?php if ($session['admin']) { ?>
                                        <li>
                                            <hr class="dropdown-divider">
                                        </li>
                                        <li><a class="dropdown-item" href="<?php echo $path; ?>user/admin"><i class="bi bi-people"></i> Admin users</a></li>
                                        <li><a class="dropdown-item" href="<?php echo $path; ?>system/list/admin"><i class="bi bi-gear"></i> Admin systems</a></li>
                                        <li><a class="dropdown-item" href="<?php echo $path; ?>shop"><i class="bi bi-shop"></i> Admin shop</a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item" href="<?php echo $path; ?>system/log"><i class="bi bi-clock-history"></i> Change log</a></li>
                                        <li><a class="dropdown-item" href="<?php echo $path; ?>system/photos/admin"><i class="bi bi-images"></i> System photos</a></li>
                                    <?php } ?>
                                    <li>
                                        <hr class="dropdown-divider">
                                    </li>
                                    <li><a class="dropdown-item" href="<?php echo $path; ?>user/logout"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
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
            <a href="https://github.com/openenergymonitor/heatpumpmonitor.org" class="oslink" style="font-size:14px">This website is open source on GitHub</a>
            <p><a href="<?php echo $path; ?>about" class="oslink">About</a> | <a href="<?php echo $path; ?>api-helper" class="oslink">API</a></p>
        </div>
    </footer>

    <script src="<?php echo $path; ?>theme/vendor/bootstrap-5.3.0/js/bootstrap.bundle.min.js" integrity="sha384-geWF76RCwLtnZ8qwWowPQNguL3RmwHVBC9FhGdlKrxdiJJigb/j/68SIy3Te4Bkz"></script>
    
    <!-- PWA Service Worker Registration -->
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                navigator.serviceWorker.register('<?php echo $path; ?>sw.js')
                    .then(function(registration) {
                        console.log('ServiceWorker registration successful with scope: ', registration.scope);
                    })
                    .catch(function(err) {
                        console.log('ServiceWorker registration failed: ', err);
                    });
            });
        }
    </script>
</body>

</html>
