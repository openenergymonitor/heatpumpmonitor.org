# Copilot Instructions for HeatpumpMonitor.org

## Project Overview
HeatpumpMonitor.org is a PHP-based web application for sharing and comparing heat pump performance data. It features a lightweight custom MVC framework with a modular architecture built for displaying energy data from linked Emoncms installations.

## Architecture & Framework

### Custom MVC Structure
- **Entry Point**: `www/index.php` - front controller handling all requests via routing
- **Routing**: `www/route.php` - URL parsing and controller dispatch
- **Core Functions**: `www/core.php` - utilities for HTTP helpers, views, sessions
- **Modules**: `www/Modules/{module}/` - self-contained features with controller/model/view pattern

### Key Architectural Patterns
- **Modules are self-contained**: Each in `www/Modules/{name}/` with:
  - `{name}_controller.php` - handles routes like `/system/view?id=123`
  - `{name}_model.php` - database operations and business logic
  - `{name}_schema.php` - database table definitions
  - Views in `views/` or inline in controller
- **Database schemas**: Defined in `{module}_schema.php` files, not migrations
- **Auto-loading**: Controllers dynamically loaded based on URL segments
- **Global objects**: Core models (`$system`, `$user`, `$system_stats`) initialized in `index.php`

## Development Workflows

### Local Development
```bash
# Docker setup (recommended)
docker compose build && docker compose up
# Access at http://localhost:8080
# Default login: admin/admin (dev mode)

# Load/update sample data and database schema
docker compose run load_dev_env_data

# Manual setup
cp www/example.settings.php www/settings.php
# Edit database credentials
```

### Docker Development Environment

#### Container Architecture
- **web**: PHP 8.1 with Apache, serves application on port 8080
- **db**: MariaDB 11.0 database with persistent volume storage
- **load_dev_env_data**: One-time data loader with configurable options

#### Environment Configuration (`default.docker-env`)
```env
DOCKER_ENV=1
EMONCMS_HOST=https://emoncms.org
MYSQL_HOST=db
MYSQL_DATABASE=heatpumpmonitor
MYSQL_USER=heatpumpmonitor
MYSQL_PASSWORD=heatpumpmonitor
DEV_ENV_LOGIN_ENABLED=true  # Enables admin:admin login
```

#### Data Loading Control
The `load_dev_env_data` service supports granular data loading via environment variables:
```bash
# Environment variables for docker-compose.yml
CLEAR_DB=1          # Clear database before loading
LOAD_USERS=1        # Create test users
LOAD_SYSTEM_META=1  # Load system metadata
LOAD_RUNNING_STATS=1    # Load summary statistics (recommended)
LOAD_MONTHLY_STATS=0    # Load monthly data (slow, use sparingly) 
LOAD_DAILY_STATS=0      # Load daily data (very slow, use sparingly)

# JSON arrays for specific systems (monthly/daily only)
LOAD_MONTHLY_STATS='[44,67,89]'  # Load only specific system IDs
LOAD_DAILY_STATS='[44]'          # Load daily data for system 44 only
```

### Database Management

#### Database Schema System
The project uses a custom declarative schema system instead of traditional migrations.

##### Schema File Structure
- **Location**: Each module contains `{module}_schema.php` 
- **Format**: PHP arrays defining table structure and field properties
- **Application**: `update_database.php` applies schema changes by comparing definitions to actual database
- **Engine**: `www/Lib/dbschemasetup.php` handles table creation, updates, and field modifications

##### Field Definition Properties
Each database field is defined as an array with the following properties:

###### Core Database Properties
- **`type`** (required): MySQL column type
  - Examples: `'int(11)'`, `'varchar(64)'`, `'text'`, `'float'`, `'tinyint(1)'`, `'JSON'`, `'date'`, `'datetime'`
- **`Null`** (optional): Whether field accepts NULL values
  - `true` (default): Allow NULL
  - `false` or `'NO'`: NOT NULL constraint
- **`Key`** (optional): Primary key designation
  - `true` or `'PRI'`: Makes field primary key
  - Automatically sets NOT NULL constraint
- **`Extra`** (optional): Auto-increment flag
  - `true` or `'auto_increment'`: Field auto-increments
- **`Default`** (optional): Default value for field
  - String values: `'default' => 'other'`
  - Numeric values: `'default' => 0`
- **`Index`** (optional): Create database index
  - `true`: Creates index with name pattern `IX_{table}_{field}`

###### UI Form Properties (system_meta specific)
- **`editable`** (optional): Whether field appears in edit forms
  - `true`: Show in edit forms
  - `false`: Read-only or system field
- **`optional`** (optional): Form validation requirement
  - `true`: Field is optional in forms
  - `false`: Required field validation
- **`name`** (optional): Human-readable label for forms
- **`helper`** (optional): Help text displayed in forms
- **`group`** (optional): Groups related fields in forms
  - Examples: `'Overview'`, `'Heat pump'`, `'Property'`, `'Metering'`
- **`options`** (optional): Dropdown/select options
  - Array of strings: `'options' => array('Option 1', 'Option 2')`
- **`unit`** (optional): Unit suffix for display
  - Examples: `'kW'`, `'°C'`, `'litres'`, `'%'`
- **`show`** (optional): Visibility in public views
  - `true` (default): Show in public system view
  - `false`: Hide from public (admin only)
- **`show_to_admin`** (optional): Admin-only visibility
- **`basic`** (optional): Include in basic/simplified forms
- **`disabled`** (optional): Disable form input (calculated fields)

###### Statistics Display Properties (stats tables)
- **`heading`** (optional): Custom column header (can include HTML like `<br>`)
- **`dp`** (optional): Decimal places for number formatting
  - Integer: Number of decimal places to display
- **`unit`** (optional): Unit suffix for display in tables

##### Schema Examples

###### Simple Table Schema
```php
$schema['manufacturers'] = array(
    'id' => array('type' => 'int(11)', 'Null' => false, 'Key' => 'PRI', 'Extra' => 'auto_increment'),
    'name' => array('type' => 'varchar(128)'),
    'website' => array('type' => 'varchar(128)')
);
```

###### Form-Enabled Schema with UI Properties
```php
$schema['system_meta'] = array(
    'location' => array(
        'type' => 'varchar(64)', 
        'editable' => true, 
        'optional' => false, 
        'name' => 'Location', 
        'helper' => 'Roughly where the heat pump is installed, to nearest city or county',
        'group' => 'Overview'
    ),
    'hp_type' => array(
        'type' => 'varchar(64)', 
        'editable' => true, 
        'optional' => false, 
        'name' => 'Heat pump type', 
        'group' => 'Heat pump', 
        'options' => array('Air Source', 'Ground Source', 'Water Source'),
        'show' => false
    )
);
```

###### Statistics Table Schema with Display Properties
```php
$schema['system_stats_daily'] = array(
    'combined_cop' => array(
        'type' => 'float', 
        'name' => 'COP', 
        'heading' => 'COP', 
        'group' => 'Stats: Combined', 
        'dp' => 1, 
        'unit' => ''
    ),
    'combined_elec_kwh' => array(
        'type' => 'float', 
        'name' => 'Electricity consumption', 
        'group' => 'Stats: Combined', 
        'dp' => 0, 
        'unit' => 'kWh'
    )
);
```

##### Schema Management Commands

###### Update Database Schema
```bash
# Apply all schema changes from all modules
docker compose run --entrypoint "php update_database.php" load_dev_env_data

# View pending changes without applying (dry run)
# Edit update_database.php temporarily to set $apply = false
```

###### Schema System Features
- **Automatic Table Creation**: Creates tables if they don't exist
- **Field Addition**: Adds missing fields to existing tables
- **Field Modification**: Updates field types, constraints, defaults
- **Index Management**: Creates indexes for fields marked with `'Index' => true`
- **Primary Key Handling**: Supports single and compound primary keys
- **Safe Updates**: Preserves existing data during schema changes
- **MySQL Engine**: Uses MYISAM engine by default

##### Schema Naming Conventions & Patterns

###### Table Naming
- **Module Tables**: Use module name as prefix (e.g., `system_meta`, `system_images`, `system_stats_daily`)
- **Lookup Tables**: Pluralized names (e.g., `users`, `manufacturers`, `installers`)
- **Junction/Mapping**: Use descriptive names (e.g., `system_access`, `user_sessions`)
- **Statistics Tables**: Include time period in name (e.g., `system_stats_daily`, `system_stats_monthly_v2`)

###### Field Naming Patterns
- **Primary Keys**: Always `id` with auto-increment
- **Foreign Keys**: `{table}_id` format (e.g., `system_id`, `manufacturer_id`, `userid`)
- **Boolean Flags**: Descriptive names (e.g., `published`, `editable`, `optional`, `uses_backup_heater`)
- **Timestamps**: Use `created`, `last_updated`, `date_uploaded` (Unix timestamps as `int(11)`)
- **URLs/Paths**: Use `_url` or `_path` suffix (e.g., `installer_url`, `image_path`)
- **Measurements**: Include unit in name where helpful (e.g., `cylinder_volume`, `flow_temp`)

###### Common Schema Patterns
- **Audit Fields**: Include `userid`, `created`, `last_updated` for tracking changes
- **Soft Deletes**: Use boolean `active` or `deleted` flag rather than hard deletes
- **Versioning**: Use `_v2` suffix for schema version updates (e.g., `system_stats_monthly_v2`)
- **Metadata Storage**: Use JSON fields for flexible data (e.g., `thumbnails`, `img_thumbnails`)
- **Enumeration Values**: Store as varchar with `options` array in schema for validation
- **Review/Approval**: Include `review_status` int and `review_comment` text for moderated content

#### Direct Database Access
```bash
# Connect to MariaDB CLI
docker compose exec db mariadb -u heatpumpmonitor -p"heatpumpmonitor" heatpumpmonitor

# Common database queries for development
# Check system metadata structure
DESCRIBE system_meta;

# View recent photo uploads with types
SELECT id, system_id, photo_type, original_filename, date_uploaded 
FROM system_images ORDER BY date_uploaded DESC LIMIT 10;

# Check system performance stats
SELECT id, combined_cop, combined_elec_kwh, combined_heat_kwh 
FROM system_stats_last30_v2 WHERE combined_cop IS NOT NULL LIMIT 10;

# View user accounts
SELECT id, username, email, admin, created FROM users;
```

#### Working with Database Schema

##### Adding New Fields to Existing Tables
1. **Edit Schema File**: Add field definition to appropriate `{module}_schema.php`
2. **Update Database**: Run `docker compose run --entrypoint "php update_database.php" load_dev_env_data`
3. **Verify Changes**: Check database structure and test functionality

##### Schema Field Examples by Use Case

###### Basic Data Fields
```php
'name' => array('type' => 'varchar(128)', 'Null' => false),
'description' => array('type' => 'text', 'Null' => true),
'value' => array('type' => 'float', 'Null' => true, 'default' => 0),
'active' => array('type' => 'tinyint(1)', 'Null' => false, 'default' => 1),
```

###### Form Input Fields with UI Properties
```php
'heat_pump_model' => array(
    'type' => 'varchar(64)', 
    'editable' => true, 
    'optional' => false, 
    'name' => 'Heat Pump Model', 
    'group' => 'Equipment',
    'helper' => 'Enter the manufacturer model number',
    'basic' => true
),
'installation_type' => array(
    'type' => 'varchar(32)', 
    'editable' => true, 
    'optional' => true, 
    'name' => 'Installation Type', 
    'group' => 'Equipment',
    'options' => array('Indoor', 'Outdoor', 'Hybrid')
),
```

###### Statistics/Reporting Fields
```php
'monthly_cop' => array(
    'type' => 'float', 
    'name' => 'Monthly COP', 
    'heading' => 'COP<br>(Monthly)', 
    'group' => 'Performance', 
    'dp' => 2, 
    'unit' => ''
),
'energy_usage' => array(
    'type' => 'float', 
    'name' => 'Energy Usage', 
    'group' => 'Consumption', 
    'dp' => 0, 
    'unit' => 'kWh'
),
```

###### File/Media Fields
```php
'logo_path' => array('type' => 'varchar(255)', 'Null' => true),
'thumbnails' => array('type' => 'JSON', 'Null' => true),
'file_size' => array('type' => 'int(11)', 'Null' => true),
'mime_type' => array('type' => 'varchar(100)', 'Null' => true),
```

#### Management Scripts with Docker
Execute any PHP management script using the Docker container:

```bash
# Update database schema
docker compose run --entrypoint "php update_database.php" load_dev_env_data

# Make user admin
docker compose run --entrypoint "php make_admin.php" load_dev_env_data

# Load installer logos
docker compose run --entrypoint "php load_installer_logos.php" load_dev_env_data

# Update system coordinates
docker compose run --entrypoint "php load_system_lat_lon.php" load_dev_env_data

# Execute any script in scripts/ directory
docker compose run --entrypoint "php scripts/max_24h_output.php" load_dev_env_data
docker compose run --entrypoint "php scripts/coldest_day.php" load_dev_env_data

# Custom data loading with specific systems
LOAD_MONTHLY_STATS='[44,67]' docker compose run load_dev_env_data
```

#### Development Data Loading
Sample data comes from live heatpumpmonitor.org via `load_dev_env_data.php`:
- **Public system metadata** (anonymized private data)
- **Performance statistics** aggregated by time period
- **User accounts** created with dummy data (admin:admin, user1, user2, etc.)
- **Heat pump models and manufacturers** from production database

### File Upload System
- **Photos**: Stored in `www/theme/img/system/{system_id}/`
- **Thumbnails**: Auto-generated using `ThumbnailGenerator` class
- **Validation**: 5MB limit, JPG/PNG/WebP only, max 4 photos per system
- **Photo Types**: Supports categorized uploads (`outdoor_unit`, `plant_room`, `other`)
- **Management**: CLI script `generate_thumbnails.php` for batch processing
- **Admin Interface**: `/system/photos/admin` for managing all uploaded photos

## Data Architecture & Models

### Core Data Models

#### System Model (`system_model.php`)
- **Database Table**: `system_meta` - Central repository for heat pump installation data
- **Key Functions**:
  - `list_public()` - Returns public systems for main listing
  - `list_admin()` - Admin view of all systems with user data
  - `get($userid, $systemid)` - Retrieve single system with access control
  - `save($userid, $systemid, $data, $full_edit)` - Update system metadata
  - `has_read_access()` / `has_write_access()` - Permission checking
- **Schema Fields**: 150+ fields covering heat pump specs, property details, performance metrics
  - Heat pump: manufacturer, model, output, type, refrigerant
  - Property: location, floor area, insulation, property type
  - Performance: COP values, energy consumption, costs
  - Configuration: metering setup, tariffs, sharing permissions

#### User Model (`user_model.php`)
- **Database Table**: `users` - Authentication and user management
- **Features**: Standard user auth with admin privileges, remember me functionality
- **Integration**: Links to systems via `userid`, dev environment allows admin:admin login

#### SystemStats Model (`system_stats_model.php`)
- **Database Tables**: Multiple time-aggregated tables
  - `system_stats_last7_v2`, `system_stats_last30_v2`, etc.
  - `system_stats_monthly_v2`, `system_stats_daily_v2`
- **Key Functions**:
  - `get_system_config_with_meta()` - Links system metadata with Emoncms feed configuration
  - `save_stats_table()` - Bulk insert of aggregated performance data
  - `get_stats()` - Retrieve performance statistics for time periods
- **Data Types**: COP values, energy consumption, flow temperatures, outside temperatures

#### SystemPhotos Model (`system_photos_model.php`)  
- **Database Table**: `system_images` - Photo uploads with metadata
- **Photo Types**: `outdoor_unit`, `plant_room`, `other` - categorized uploads
- **Features**: Automatic thumbnail generation, MIME validation, 5MB limit
- **File Storage**: `www/theme/img/system/{system_id}/` with JSON thumbnail metadata

### Specialized Models

#### Heatpump Model (`heatpump_model.php`)
- **Database Table**: `heatpump_model` - Master database of heat pump specifications
- **Key Functions**:
  - `get_list()` - All heat pump models with manufacturer data
  - `find_systems_with_heatpump()` - Systems using specific heat pump model
  - `populate_table()` - Auto-populate from system data
  - `add()` / `update()` - CRUD operations for heat pump entries
- **Integration**: Links manufacturers with specific models, tracks real-world performance

#### Installer Model (`installer_model.php`)
- **Database Table**: `installers` - Database of heat pump installers
- **Features**: Logo management, contact details, system count tracking
- **Logo System**: Automatic logo fetching and storage in `theme/img/installers/`

#### Manufacturer Model (`manufacturer_model.php`)
- **Database Table**: `manufacturers` - Heat pump manufacturer registry
- **Functions**: Name-based lookups, manufacturer standardization

#### MyHeatPump Model (`myheatpump_model.php`)
- **Dashboard Backend**: Powers the detailed dashboard view
- **Database Table**: `myheatpump_daily_stats` - Detailed daily performance data
- **Features**: Complex statistical processing, COP calculations, efficiency analysis
- **Integration**: Direct connection to Emoncms feeds for real-time data

### External Integration
- **Emoncms**: Remote data source via API (`$settings['emoncms_host']`)
  - Each system has `app_id` and `readkey` for secure data access
  - Feed configuration maps to specific data points (electricity, heat, temperatures)
  - Real-time and historical data aggregation
- **Caching**: Redis for performance data (optional, gracefully degrades)
- **APIs**: RESTful endpoints for data access and system management

## UI & Frontend

### Technology Stack
- **Vue.js 2**: Interactive components with global `Vue` instance
- **Bootstrap 5.3.0**: CSS framework for responsive design (use `bg-*` classes for badges, not `badge-*`)
- **Axios**: HTTP client for API calls
- **Custom CSS**: Module-specific styles in `{module}_view.css`

### Component Patterns
- **Mixins**: Reusable Vue functionality in `Modules/system/photo_*.js`
  - `PhotoLightboxMixin`: Gallery viewing with keyboard navigation
  - `PhotoUploadMixin`: Drag-drop upload with progress tracking
- **Template Includes**: Shared HTML in `photo_lightbox_template.html`
- **Utility Libraries**: `PhotoUtils` for thumbnail selection and file validation

## Application Modules & Features

### Core Application Modules

#### System Module (`www/Modules/system/`)
**Purpose**: Central heat pump system management
- **Controllers**: 
  - `system_controller.php` - CRUD operations, system listing, detailed views
  - Routes: `/system/list`, `/system/view?id=123`, `/system/edit`, `/system/save`
- **Views**: 
  - `system_list.php` - Configurable system listing with filtering (topofthescops, heatpumpfabric, costs templates)
  - `system_view.php` - Detailed system information with Vue.js integration
- **Features**:
  - Public/admin system listings with template views
  - Advanced filtering and sorting capabilities
  - System photo management integration
  - Performance statistics integration
  - Links to dashboard, monthly, daily, and heat loss analysis

#### Dashboard Module (`www/Modules/dashboard/`)
**Purpose**: Interactive heat pump performance dashboard
- **Controllers**: 
  - `dashboard_controller.php` - Dashboard access control and data routing
  - `myheatpump_controller.php` - Advanced dashboard with real-time data processing
- **Features**:
  - Real-time performance monitoring with Vue.js
  - Power consumption vs heat output analysis
  - COP (Coefficient of Performance) calculations and visualization
  - Historical data analysis with configurable time ranges
  - Flow temperature optimization tools
  - Defrost cycle detection and analysis
- **Frontend**: 
  - `myheatpump.js` - Core dashboard functionality
  - `myheatpump_powergraph.js` - Performance visualization
  - `myheatpump_bargraph.js` - Bar chart visualizations

#### Map Module (`www/Modules/map/`)
**Purpose**: Geographic visualization of heat pump systems
- **Controller**: `map_controller.php` - Location services and system mapping
- **Features**:
  - Interactive OpenLayers map with system markers
  - System filtering by performance metrics (COP, installer, type)
  - Color-coded markers based on installer or performance
  - Popup overlays with system details
  - Location search integration (OpenCage Data API)
- **Frontend**: `map_view.js` - Map interaction and system filtering

#### Heatpump Module (`www/Modules/heatpump/`)
**Purpose**: Heat pump model database and specifications
- **Controller**: `heatpump_controller.php` - Heat pump model management
- **Features**:
  - Master database of heat pump models and specifications
  - Performance comparison tools
  - Integration with real-world system performance data
  - Admin tools for managing heat pump database
  - Unmatched system detection and resolution

#### Installer Module (`www/Modules/installer/`)
**Purpose**: Heat pump installer directory and management
- **Controller**: `installer_controller.php` - Installer database management
- **Features**:
  - Installer directory with contact information
  - Logo management and display
  - System count tracking per installer
  - Training and certification tracking
  - Integration with system listings

#### Histogram Module (`www/Modules/histogram/`)
**Purpose**: Advanced performance analysis and visualization
- **Controller**: `histogram_controller.php` - Performance histogram generation
- **Features**:
  - COP distribution analysis
  - Flow temperature vs performance correlation
  - Carnot efficiency analysis
  - Weather compensation curve analysis
  - Energy consumption pattern analysis

#### Manufacturer Module (`www/Modules/manufacturer/`)
**Purpose**: Heat pump manufacturer database
- **Controller**: `manufacturer_controller.php` - Manufacturer data management
- **Features**: 
  - Standardized manufacturer names and information
  - Integration with heat pump model database
  - Brand consistency across system listings

### Analysis & Reporting Features

#### Heat Loss Analysis (`www/views/heatloss.php`)
**Purpose**: Building heat loss calculation and heat pump sizing
- **Features**:
  - Heat loss coefficient calculation from real performance data
  - Design vs actual heat loss comparison
  - Heat pump sizing recommendations
  - Weather compensation analysis

#### Monthly/Daily Analysis (`www/views/monthly.php`, `www/views/daily.php`)
**Purpose**: Time-based performance analysis
- **Features**:
  - Seasonal performance variation analysis
  - Monthly and daily COP trends
  - Energy consumption patterns
  - Cost analysis and projections

#### Comparison Tools (`www/views/compare.php`)
**Purpose**: Multi-system performance comparison
- **Features**:
  - Side-by-side system comparison
  - Performance benchmarking
  - Efficiency ranking and analysis

### API & Data Access (`www/views/api.php`)
**Purpose**: Programmatic access to system data
- **Endpoints**:
  - `/system/list/public.json` - Public system metadata
  - `/system/stats/{period}` - Performance statistics
  - `/installer/list.json` - Installer directory
  - `/heatpump/list.json` - Heat pump model database
- **Features**: RESTful JSON APIs for external integration

### Photo Upload System Architecture

#### Database Schema
- **Table**: `system_images` in `system_schema.php`
- **Key Fields**: 
  - `photo_type` varchar(50) with default 'other'
  - `image_path`, `original_filename`, `thumbnails` (JSON)
  - `system_id` for linking to heat pump systems

#### Backend API
- **Model**: `SystemPhotos` in `system_photos_model.php`
- **Key Methods**:
  - `upload_photo($userid)` - handles file upload with photo_type validation
  - `get_photos($userid, $system_id)` - returns photos with type information
  - `get_all_photos_admin($userid, $page, $limit)` - admin interface pagination
- **Validation**: MIME type checking with `finfo_file()`, 5MB limit, max 4 photos per system
- **Prepared Statements**: Critical - ensure parameter count matches placeholder count in bind_param

#### Frontend Implementation
- **Main View**: `system_view.php` with Vue.js integration
- **Photo Type Boxes**: Dedicated UI containers for `outdoor_unit`, `plant_room`, `other`
- **Conditional Event Handlers**: Upload disabled when photos exist for specific types
- **CSS Grid Layout**: Responsive photo type containers in `system_view.css`
- **Admin Interface**: `system_photos_admin_view.php` with photo type badges and pagination

#### File Organization
- **Uploads**: `www/theme/img/system/{system_id}/` directory structure
- **Thumbnails**: Auto-generated in multiple sizes (80x60, 150, 300, etc.)
- **CLI Management**: `generate_thumbnails.php` for batch thumbnail processing

#### UX Patterns
- **Type-specific Upload**: Placeholder boxes show when no photo exists for outdoor_unit/plant_room
- **Visual Feedback**: Drag states, progress bars, colored badges for photo types
- **Admin Management**: Sortable table with photo type column, delete functionality
- **Access Control**: User permissions checked for upload/delete operations

## Key Conventions

### File Organization
- **Views**: Either in `Modules/{name}/views/` or `views/` in project root
- **Assets**: CSS/JS in module directories, images in `www/theme/img/`
- **Global utilities**: In `www/Lib/` directory

### Database Patterns
- **Integer IDs**: Auto-incrementing primary keys using `'Key' => 'PRI', 'Extra' => 'auto_increment'`
- **JSON fields**: Used for thumbnail metadata, complex data structures (`'type' => 'JSON'`)
- **Boolean fields**: Use `'type' => 'tinyint(1)'` for boolean values
- **Prepared statements**: Always use for user input
- **Access control**: Check permissions in models via `has_read_access()`/`has_write_access()`
- **Nullable vs Required**: Use `'Null' => false` for required fields, `'Null' => true` (default) for optional
- **Default Values**: Always specify defaults for non-nullable fields (`'default' => 0` for integers, `'default' => 'value'` for strings)
- **Foreign Keys**: Typically use `{table}_id` naming pattern (e.g., `system_id`, `user_id`, `manufacturer_id`)
- **Text vs Varchar**: Use `varchar(n)` for bounded text, `text` for unlimited content
- **Date Handling**: Use `int(11)` for Unix timestamps or `date`/`datetime` for MySQL date types

### API Endpoints
- **Format suffix**: `.json` for API responses (e.g., `/system/photos.json`)
- **Route actions**: Controller methods map to URL segments (`/system/upload-photo`)
- **Response format**: `array("success" => bool, "message" => string, ...)`

### Security Practices
- **Input validation**: Use `get()`, `post()`, `prop()` helpers from `core.php`
- **File uploads**: Validate MIME types with `finfo_file()`, not just extensions
- **Directory traversal**: Use `realpath()` to validate included files
- **Admin checks**: Always verify `$session['admin']` for admin-only features

## Development Tips

### Adding New Features
1. Create module directory: `www/Modules/newfeature/`
2. Add controller function: `function newfeature_controller() { ... }`
3. Define schema in `newfeature_schema.php`
4. Update `index.php` to initialize any global model objects
5. Add database access in model with proper permissions checking

### Photo System Integration
- **Upload**: Use `SystemPhotos->upload_photo()` for backend
- **Frontend**: Include photo mixins and utility files
- **Thumbnails**: Automatic generation, use `PhotoUtils.selectThumbnail()` for display
- **Admin**: Photos manageable via `/system/photos/admin` interface
- **Photo Types**: Three categories - `outdoor_unit`, `plant_room`, `other`
- **Type-specific UI**: Dedicated upload boxes for outdoor unit and plant room photos
- **Conditional Upload**: Upload handlers disabled when specific photo types already exist

### Data Loading
- **Development**: `load_dev_env_data.php` with environment variables to control what loads
- **Production**: Link to existing Emoncms installation via API keys
- **Performance**: Use Redis caching where available, degrade gracefully if not

## Debugging & Development Commands

### Docker Environment Management

#### Container Operations
```bash
# Start development environment
docker compose up -d
# Access at http://localhost:8080 (admin:admin for dev login)

# Check container status and health
docker compose ps
docker compose logs web    # PHP/Apache logs  
docker compose logs db     # MariaDB logs

# Stop and clean environment
docker compose down
docker compose down -v    # Remove volumes (database data)
docker compose up --remove-orphans  # Clean orphaned containers

# Rebuild containers after Dockerfile changes
docker compose build --no-cache
```

#### Data Management Operations
```bash
# Full development data reload (slow, uses server bandwidth)
RELOAD_ALL=1 docker compose run load_dev_env_data

# Quick setup - just users, systems, and summary stats
docker compose run load_dev_env_data  # Uses default docker-compose.yml settings

# Load specific systems' detailed data
LOAD_MONTHLY_STATS='[44,67,89]' LOAD_DAILY_STATS='[44]' docker compose run load_dev_env_data

# Update database schema only
docker compose run --entrypoint "php update_database.php" load_dev_env_data
```

### Database Access & Debugging

#### Direct MariaDB Access
```bash
# Connect to database CLI
docker compose exec db mariadb -u heatpumpmonitor -p"heatpumpmonitor" heatpumpmonitor

# Execute single SQL commands from command line
docker compose exec db mariadb -u heatpumpmonitor -p"heatpumpmonitor" heatpumpmonitor -e "SHOW TABLES;"

# Common debugging queries
docker compose exec db mariadb -u heatpumpmonitor -p"heatpumpmonitor" heatpumpmonitor -e "
  SELECT COUNT(*) as system_count FROM system_meta WHERE published=1;
  SELECT COUNT(*) as user_count FROM users;
  SELECT COUNT(*) as image_count FROM system_images;
  SELECT photo_type, COUNT(*) FROM system_images GROUP BY photo_type;
"
```

#### Schema Inspection Queries
```sql
-- Check table structures after schema updates
DESCRIBE system_meta;
DESCRIBE system_images;
DESCRIBE heatpump_model;

-- Verify data integrity
SELECT s.id, s.location, COUNT(si.id) as photo_count 
FROM system_meta s LEFT JOIN system_images si ON s.id = si.system_id 
GROUP BY s.id LIMIT 10;

-- Performance data validation
SELECT id, combined_cop, combined_elec_kwh, combined_heat_kwh, data_length
FROM system_stats_last30_v2 
WHERE combined_cop IS NOT NULL 
ORDER BY combined_cop DESC LIMIT 10;
```

### Management Script Execution

#### User Management
```bash
# Make user an admin (edit username in make_admin.php first)
docker compose run --entrypoint "php make_admin.php" load_dev_env_data

# Check user permissions
docker compose exec db mariadb -u heatpumpmonitor -p"heatpumpmonitor" heatpumpmonitor -e "SELECT id, username, admin FROM users;"
```

#### Data Processing Scripts
```bash
# Load installer logos from websites
docker compose run --entrypoint "php load_installer_logos.php" load_dev_env_data

# Update system geographical coordinates
docker compose run --entrypoint "php load_system_lat_lon.php" load_dev_env_data

# Various analysis scripts
docker compose run --entrypoint "php scripts/coldest_day.php" load_dev_env_data
docker compose run --entrypoint "php scripts/max_24h_output.php" load_dev_env_data
docker compose run --entrypoint "php scripts/max_4h_output.php" load_dev_env_data
```

#### File Management
```bash
# Access container filesystem for debugging
docker compose exec web bash
docker compose exec web ls -la /var/www/heatpumpmonitororg/

# Copy files from container
docker compose cp web:/var/www/heatpumpmonitororg/theme/img/system/44/ ./local_photos/

# Check photo upload permissions and structure
docker compose exec web ls -la /var/www/heatpumpmonitororg/theme/img/system/
```

### Development Debugging Strategies

#### PHP Debugging
- **Error Logs**: `docker compose logs web` for PHP errors and warnings
- **Xdebug**: Configured in `config/xdebug.ini`, use with IDE breakpoints
- **Variable Inspection**: Use `var_dump()` and check in browser or logs
- **Database Queries**: Enable MySQL query logging for SQL debugging

#### Frontend Debugging
- **Vue.js**: Browser dev tools for component state and events
- **Network**: Check browser Network tab for API response errors
- **Console**: JavaScript errors and Vue warnings appear in browser console
- **Bootstrap**: Use `bg-*` classes for badges in Bootstrap 5, not `badge-*`

#### Common Issues & Solutions
- **Parameter Binding**: Ensure bind_param type string matches placeholder count
- **File Permissions**: Check `www/theme/img/` write permissions for photo uploads
- **Photo Types**: Verify `system_images.photo_type` values: `outdoor_unit`, `plant_room`, `other`
- **API Responses**: Check `.json` endpoint responses return valid JSON
- **Database Connection**: Verify `www/settings.php` matches container environment variables

#### Performance Analysis
```bash
# Monitor container resource usage
docker stats

# Check database performance
docker compose exec db mariadb -u heatpumpmonitor -p"heatpumpmonitor" heatpumpmonitor -e "SHOW PROCESSLIST;"

# Analyze slow queries (if enabled)
docker compose exec db mariadb -u heatpumpmonitor -p"heatpumpmonitor" heatpumpmonitor -e "SELECT * FROM information_schema.PROCESSLIST WHERE TIME > 5;"
```

This project emphasizes simplicity and direct patterns over complex frameworks. When adding features, follow the existing modular structure and lightweight approach rather than introducing heavy dependencies.
