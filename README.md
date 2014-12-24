PatchMD
==============

Patch Management Dashboard
===============
Patch Management Dashboard does one thing, but it does it well:
* Monitors for needed patches on your nodes.

In a future release (Hopefully sometime in August), it will give you the ability to suppress patches on
a per-server or a global basis, as well as tell the system to install a single package, or fully update a
server.

Compatible with: Debian Wheezy+/Ubuntu 12.04+/CentOS 5.x+/Red Hat EL5+/Fedora FC19+

News
===============

v1.0-RC1 is now available.  You can grab a copy [here](https://github.com/PatchDashboard/patchdashboard/releases/tag/v1.0-RC1) or by clicking on the one you want below:

* [zip](https://github.com/PatchDashboard/patchdashboard/archive/v1.0-RC1.zip)
* [tar.gz](https://github.com/PatchDashboard/patchdashboard/archive/v1.0-RC1.tar.gz)

Please give it a spin. If you have any issues, please submit a [new issue](https://github.com/PatchDashboard/patchdashboard/issues/new)


Install
===============

To install:

* simply clone this git (git clone https://github.com/PatchDashboard/patchdashboard)
* cd into patchdashboard (cd /opt/patchdashboard)
* run install.sh (./install.sh)

It will ask you some questions. Simply provide the answers, or accept the default answers found in the "[]" boxes
If it does not have anything in "[]", you must provide an answer.

Finally To add a node:
* run "/opt/patch_manager/add_server.sh"
 * follow instructions

On each node:

If you want to do the push method, follow the instructions below.
* make sure root can log in, and is not blocked by sshd_config directives
* if root cannot log in (their password is hashed, but SSH allows them), make sure to copy the contents of "id_rsa.pub" in /root/.ssh into /root/.ssh/authorized_keys on each node
 * after adding the shared key, from the patch server, ssh into the node as root to make sure the keypair works.
* if root can log in (they have a password set), run this from the patch server:
 * ssh-copy-id root@SERVER_IP (change SERVER_IP to the nodes IP address)

If you want to use the easier pull method:
* go to your PatchDashboard Web UI
* Click on "Add a Server"
* Follow the second set of instructions, which will look something like this
  * "run the following on each node"
    * "curl https://1.1.1.1/client/client_installer.php|bash"

===============
##Links

To discuss this product, or help direct the future of this project, join our [forums](http://community.patchdashboard.com)
To keep up-to-date on the direction of this project, you are free to stop by the [blog](http://patchdashboard.com) (still in progress)


TODO: NOT MUCH!
===============

* Complete installer script (WIP - please report errors and suggest improvements) **Done for Ubuntu/Debian/Fedora/RHEL/CentOS**
* Add more distros (This one will be a continual one)
* ~~make everything database-driven~~
* ~~Configure patch suppression~~
* Configure Patch Suppression via the web UI. Back-end and DB are fully capable now. **In Progress -V1.0.1**
* configure ability to install patches from the web interface **In Testing Phase**
* configure ability to patch windows hosts **On roadmap for v1.5**
* ~~configure logins~~

===============

ROADMAP:
===============
* v1.0 RC1 -- ETC 2014-12-24
  * Will be able to use Push or Pull methods to manage servers.  Pull is by far the easiest method
  * Admin of servers and users is complete in this build
  * Installing via the web UI will be fully implemented by RC1

* v1.0 RC2 -- ETC 2015-01-16
  * All the things we have done in v1.0 RC1, but with QA blessing

* v1.0 -- ETC 2015-02-01
  * All things in the previous releases, but with Security Engineering approval

* v1.0.1 -- ETC 2015-02-15
  * Suppression implemented

* v1.1
  * First integration with off-site CVE database

* v1.2
  * Addition of a plugin management system

* v1.3
  * Alpha testing of Windows Server management

* v1.4
  * Beta testing of Windows Server management

* v1.5
  * Stable release of Windows Server management

DEVELOPERS:
===============
* jonsjava (Creator/Project guide/Underpinning Developer/PHP & BASH Developer/DBA) https://github.com/jonsjava
* metalcated (BASH Developer) https://github.com/metalcated
* tycodym (Security Engineer) https://github.com/tycodym

>If you like what we're doing, we could always use some donations to help with hosting costs.
>
> <a href="https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=KFELDM6WDEHLA&lc=US&item_name=PatchDashboard&item_number=GitHub%20PMDB&currency_code=USD&bn=PP%2dDonationsBF%3abtn_donate_LG%2egif%3aNonHosted"><img src="https://www.paypalobjects.com/en_US/i/btn/btn_donate_SM.gif" alt="[paypal]" /> </a>


A special thanks to Josh Reichardt (http://thepracticalsysadmin.com/) who helped a ton with my early release. You rock!
