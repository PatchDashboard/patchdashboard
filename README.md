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

* simply clone this git (git clone https://github.com/jonsjava/patchdashboard.git)
* cd into patchdashboard (cd /opt/patchdashboard)
* run install.sh (./install.sh)

It will ask you some questions. Simply provide the answers, or accept the default answers found in the "[]" boxes
If it does not have anything in "[]", you must provide an answer.

Finally To add a node:
* run "/opt/patch_manager/add_server.sh"
 * follow instructions

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
* Configure patch suppression **In the works. Complete soon**
* configure ability to install patches from the web interface **In the works. Complete soon**
* configure ability to patch windows hosts
* configure logins, allowing for tie-in to LDAP/AD, and possibly a SSO solution like RADIUS **In the works. Complete soon**

===============

DEVELOPERS:
===============
JonsJava (Creator/Project guide/Underpinning Developer) https://github.com/jonsjava
metalcated (Lead Developer) https://github.com/metalcated
wilsonma08 (Bash Developer) https://github.com/wilsonma08
ejbvanc (PHP Developer) https://github.com/ejbvanc

A special thanks to Josh Reichardt (http://thepracticalsysadmin.com/) who sat with me for hours helping me get this as stable as it is right now.
