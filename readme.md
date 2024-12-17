# HeatpumpMonitor.org

An open source initiative to share and compare heat pump performance data.

### See: [https://heatpumpmonitor.org](https://heatpumpmonitor.org)

![heatpumpmonitor.png](heatpumpmonitor.png)

------------------------------------------------------------------------------------------------------------------

## Install on existing Apache2 server

Install public site content in /var/www

    sudo ln -s /home/USERNAME/heatpumpmonitor.org/www/ /var/www/heatpumpmonitororg
    
Create a mariadb/mysql database:

    CREATE DATABASE heatpumpmonitor;
    
Copy example.settings.php:

    cp www/example.settings.php settings.php
    
Modify database credentials to match your system

Load public data from heatpumpmonitor.org to create functioning development environment

    php load_dev_env_data.php

Access the site by putting http://localhost/heatpumpmonitororg/ in the browser.

Login using 'Self hosted data' and username and password: admin:admin

-----------------------------------------------------------------------------------------------------------------------
## Helpdesk and Support

###  Helpdesk Support
A support helpdesk will assist users with issues related to Docker, installation, and troubleshooting.

- **Platform**:  
  Use the link to navigate to the heatpumpmonitor forum commuitity:
  - Forum Discussions: [Discussions Page](https://community.openenergymonitor.org/c/hardware/heatpump/47)
  
- **Support Categories**:
  - **Docker Installation and Setup**
  - **Troubleshooting Containers**
  - **General Questions**

---
## Docker Setup

Running the system through Docker might not using as much effort since the system already has its own Docker config.

1. Locate the location of the file in your workspace first using command below in the terminal.
(Remember, file location and name might be different due to user's own customization)

    C:\\workspace\heatpumpmonitor.org

2. Proceed by inserting these two commands.

    docker-compose build
    docker-compose up

Site should now be running on http://localhost:8080

---
## Troubleshooting Containers

#### **Issue 1: Port Conflict (Port 8080 Already in Use)**

**Error Message**: Error response from daemon: Ports are not available: exposing port TCP 0.0.0.0:8080 -> 0.0.0.0:0: listen tcp 0.0.0.0:8080: bind: Only one usage of each socket address (protocol/network address/port) is normally permitted.

*Cause**:  
Port `8080` is already being used by another process.

**Solution**:  
1.**Identify the Process Using Port 8080**:  
   Run the following command in the Command Prompt as Administrator:
```bash
   netstat -aon | findstr :8080
```
2.**Terminate the Process**:
    Use the PID obtained in Step 1 to terminate the process:
```bash
   taskkill /PID <PID> /F
```
Replace <PID> with the actual Process ID.

3.**Step 3: Check for Docker Containers Using the Port**

3.1) List all running Docker containers:

```bash
   docker ps
```
3.2) Look for any containers that might be using port 8080.

3.3) Stop the container using:

```bash
   docker stop <container_id>
```
4.**Step 4: Restart Docker**
Restart Docker from the Docker Desktop interface or by running the following commands:
```bash
    net stop com.docker.service
    net start com.docker.service
```
#### **Issue 2: Docker Containers Fail to Build**

**Solution**:  

1.**Verify Docker and Docker Compose installation:**:  
```bash
    docker --version
    docker-compose --version
```
2.**Rebuild the containers:**
```bash
    docker-compose down
    docker-compose build
    docker-compose up
```
## General Questions

1. **Q: What are the system requirements to run the Heat Pump Monitor?**

**A:**

    -Minimum RAM: 4GB

    -Docker version: 20.10 or higher

    -Operating Systems: Linux, Windows, or macOS

    -Required tools: Git, Docker, Docker Compose
    
2. **Can I run the system without Docker?**

**A:**

-Clone the repository.

-Set up a MariaDB/MySQL database.

-Copy example.settings.php to settings.php and configure the database.

-Load public data using:
```bash
php load_dev_env_data.php
```
-Access the site via http://localhost/heatpumpmonitororg/.

3. **Q: How do I load real data into the system?**

**A:** Use the provided script to load development data:
```bash
php load_dev_env_data.php
```

4. **Can I integrate this system with other IoT platforms?**
**A:** Yes, the system supports API integrations. Refer to the [API documentation.](http://localhost:8080/api-helper)
