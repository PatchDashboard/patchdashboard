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
* jonsjava (Creator/Project guide/Underpinning Developer) https://github.com/jonsjava
* metalcated (Lead Developer) https://github.com/metalcated
* wilsonma08 (Bash Developer) https://github.com/wilsonma08
* ejbvanc (PHP Developer) https://github.com/ejbvanc

If you like what we're doing, we could always use some donations to help with hosting costs.

> <form action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_top"><input type="hidden" name="cmd" value="_s-xclick"><input type="hidden" name="encrypted" value="-----BEGIN PKCS7-----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-----END PKCS7-----"><input type="image" src="https://www.paypalobjects.com/en_US/i/btn/btn_donateCC_LG.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!"><img alt="" border="0" src="https://www.paypalobjects.com/en_US/i/scr/pixel.gif" width="1" height="1"></form>


A special thanks to Josh Reichardt (http://thepracticalsysadmin.com/) who helped a ton with my early release. You rock!
