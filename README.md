patchdashboard
==============

Patch Management Dashboard
===============
Patch Management Dashboard does one thing, but it does it well:
* Monitors for needed patches on your nodes.

In a future release (Hopefully sometime in August), it will give you the ability to suppress patches on
a per-server or a global basis, as well as tell the system to install a single package, or fully update a
server.

===============
## Install

To install:
1. install the full LAMP stack on your distro of choice. If you're not sure how to do that:
 * https://community.rackspace.com/products/f/25/t/49
2. create a database user with a password and a database for this system.
3. simply clone this git (git clone https://github.com/jonsjava/patchdashboard.git)
4. cd into patchdashboard (cd patchdashboard)
5. run install.sh (./install.sh)

It will ask you some questions. Simply provide the answers, or accept the default answers found in the "[]" boxes
If it doesn't have anything in "[]", you must provide an answer.
===============
TODO: A LOT!


* ~~Complete installer script~~
* Add more distros
* ~~make everything database-driven~~
* Configure patch suppression
* configure ability to install patches from the web interface
* configure logins, allowing for tie-in to LDAP/AD, and possibly a SSO solution like RADIUS

===============

A special thanks to Josh Reichardt (http://thepracticalsysadmin.com/) who sat with me for hours helping me get this as stable as it is right now.