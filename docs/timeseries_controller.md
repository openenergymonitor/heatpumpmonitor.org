# Timeseries Controller Documentation

The timeseries_controller.php file handles the retrieval and processing of time series data in the system. It provides two main functionalities:

1. Fetching system configuration details for a specified system.
2. Fetching time series data for selected feeds within a given time range.


## File Location

>/www/Modules/timeseries/timeseries_controller.php


## Dependencies

- Global Variables Used:

    - $session: Contains session-related data for the current user.
    - $route: Handles routing and determines the action to execute.
    - $system_stats: Used for retrieving system statistics and configurations.
    - $mysqli: Database connection object.
    - $settings: Global system settings.

- External Dependencies:

    - Backend API: Communicates with a backend API for fetching data.
    - PHP Functions:
        - file_get_contents(): Used for making API calls.
        - json_decode(): Parses JSON responses from the backend.


## Supported Actions

1. **Available Configuration (available)**

This action fetches the system's configuration details, including feed information, while removing sensitive details such as API keys.

**Input Parameters**

|Parameter|	Type|	Description|	Required|
|----|-------|-----------|-----------------|
|id|	int|	System ID|	Yes|

**Output Format**

|Field|	Type|	Description|
|----|------|------------|
|success|	bool|	Indicates if the request was successful|
|feeds|	array|	List of available feeds and their details|

2. **Fetch Time Series Data (data)**

This action retrieves time series data for specified feeds within a given time range.

**Input Parameters**

|Parameter|	Type|	Description|	Required|	Default|
|---|---|---|---|---|
|id	|int	|System ID|	Yes|	
|feeds|	string	|Comma-separated list of feed keys|	Yes	|
|start|	int|	Start timestamp (Unix)|	Yes|	
|end	|int|	End timestamp (Unix)	|Yes|	
|interval	|int	|Interval between data points in seconds	|Yes	|
|average	|int|	Whether to average data points (0 or 1)	|No|	0|
|delta	|int	|Whether to compute deltas between data points	|No|	0|
|timeformat	|string	|Time format (unix, unixms, excel, iso8601, notime)|	No|	unixms|


## Error Handling

The module validates input parameters and provides error responses in the following format:

> {
>"success": false,
>  
>  "message": "Invalid time format"
}

## Security Considerations

1. Direct access to this file is restricted using:

> defined('EMONCMS_EXEC') or die('Restricted access');

2. Sensitive data such as API keys and server details are removed before returning responses.

## How to Use

1. **Add Route Configuration:** Ensure the routes for *available* and *data* actions are defined in the system routing configuration.
2. **Call the Controller:** Use appropriate parameters to call the *timeseries_controller()* function
3. **Parse Responses:** Process the JSON response to use the configuration or time series data as needed.