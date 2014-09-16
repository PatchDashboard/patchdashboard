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
##Install

To install:
* install the full LAMP stack on your distro of choice. If you're not sure how to do that:
 * https://community.rackspace.com/products/f/25/t/49
* create a database user with a password and a database for this system.
* simply clone this git (git clone https://github.com/jonsjava/patchdashboard.git)
* cd into patchdashboard (cd patchdashboard)
* run install.sh (./install.sh)

It will ask you some questions. Simply provide the answers, or accept the default answers found in the "[]" boxes
If it doesn't have anything in "[]", you must provide an answer.

Finally:
* add /root/scripts/start_package_list.sh to your cron (every 2 hours is what I do)
* same for /root/scripts/start_patch_check.sh


To add a node:
* run "/root/scripts/add_server.sh"
 * follow prompts


On each node:

* make sure root can log in, and is not blocked by sshd_config directives
* if root cannot log in (their password is hashed, but SSH allows them), make sure to copy the contents of "id_rsa.pub" in /root/.ssh into /root/.ssh/authorized_keys on each node
 * after adding the shared key, from the patch server, ssh into the node as root to make sure the keypair works.
* if root can log in (they have a password set), run this from the patch server:
 * ssh-copy-id root@SERVER_IP (change SERVER_IP to the nodes IP address)



TODO: A LOT!
===============

* ~~Complete installer script~~
* Add more distros
* ~~make everything database-driven~~
* Configure patch suppression
* configure ability to install patches from the web interface
* configure logins, allowing for tie-in to LDAP/AD, and possibly a SSO solution like RADIUS

===============

A special thanks to Josh Reichardt (http://thepracticalsysadmin.com/) who sat with me for hours helping me get this as stable as it is right now.
