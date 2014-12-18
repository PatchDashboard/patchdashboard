patchdashboard
==============

Patch Management Dashboard
===============
Patch Management Dashboard does one thing, but it does it well:
* Monitors for needed patches on your nodes.

In a future release (Hopefully sometime in August), it will give you the ability to suppress patches on
a per-server or a global basis, as well as tell the system to install a single package, or fully update a
server.

Compatable with: Debian Wheezy+/Ubuntu 12.04+/CentOS 5.x+/Red Hat EL5+/Fedora FC19+
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


===============
##Links

To discuss this product, or help direct the future of this project, join our [forums](http://community.patchdashboard.com)
To keep up-to-date on the direction of this project, you are free to stop by the [blog](http://patchdashboard.com) (still in progress)


TODO: A LOT!
===============

* ~~Complete installer script~~
* Add more distros (This one will be a continual one)
* ~~make everything database-driven~~
* ~~Configure patch suppression~~
* Configure Patch Suppression via the web UI. Back-end and DB are fully capable now. **In Progress**
* configure ability to install patches from the web interface **In Testing Phase**
* configure ability to patch windows hosts **On roadmap for v1.5**
* ~~configure logins~~

===============

DEVELOPERS:
===============
* jonsjava (Creator/Project guide/Underpinning Developer/PHP & BASH Developer/DBA) https://github.com/jonsjava
* metalcated (BASH Developer) https://github.com/metalcated
* tycodym (Security Engineer) https://github.com/tycodym

>If you like what we're doing, we could always use some donations to help with hosting costs.
>
> <a href="https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=KFELDM6WDEHLA&lc=US&item_name=PatchDashboard&item_number=GitHub%20PMDB&currency_code=USD&bn=PP%2dDonationsBF%3abtn_donate_LG%2egif%3aNonHosted"><img src="https://www.paypalobjects.com/en_US/i/btn/btn_donate_SM.gif" alt="[paypal]" /> </a>


A special thanks to Josh Reichardt (http://thepracticalsysadmin.com/) who helped a ton with my early release. You rock!
