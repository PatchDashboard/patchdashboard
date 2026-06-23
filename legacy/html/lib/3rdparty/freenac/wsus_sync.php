<?php

/**
 * PHP version 5
 *
 * LICENSE: This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as published
 * by the Free Software Foundation.
 *
 * @package		FreeNAC
 * @author      Wolfram Strauss, Hector Ortiz (FreeNAC Core Team)
 * @copyright   2006 FreeNAC
 * @license     http://www.gnu.org/copyleft/gpl.html   GNU Public License Version 2
 * @version     CVS: $Id:$
 * @link        http://www.freenac.net
 *
 */
/**
 * Obtain update status from the wsus database and store locally.
 */
/* * *****************************
  FUNCTIONS
 * ***************************** */

/**
 *  This function converts the datetime retrieved from MSSQL into MySQL datetime format
 */
function convert_date($date) {
    $date_array = getdate(strtotime($date));
    $date = $date_array['year'] . '-';
    $date_array['mon'] < 10 ? $date.='0' . $date_array['mon'] . '-' : $date.=$date_array['mon'] . '-';
    $date_array['mday'] < 10 ? $date.='0' . $date_array['mday'] . ' ' : $date.=$date_array['mday'] . ' ';
    $date_array['hours'] < 10 ? $date.='0' . $date_array['hours'] . ':' : $date.=$date_array['hours'] . ':';
    $date_array['minutes'] < 10 ? $date.='0' . $date_array['minutes'] . ':' : $date.=$date_array['minutes'] . ':';
    $date_array['seconds'] < 10 ? $date.='0' . $date_array['seconds'] : $date.=$date_array['seconds'];
    return $date;
}

/**
 * Ensures that $string is mysql safe
 */
function validate($string) {
    rtrim($string, ' ');
    if (get_magic_quotes_gpc()) {
        $value = stripslashes($string);
    }
    if (!is_numeric($string)) {
        $string = mysql_real_escape_string($string);
    }
    return $string;
}

/**
 * Connect to the WSUS server
 */
function dbwsus_connect() {
    global $conf, $wsus_dbuser, $wsus_dbpass, $logger;
    $logger->debug("Connecting to " . $conf->wsus_dbalias . " " . $conf->wsus_db, 1);
    $msconnect = mssql_connect($conf->wsus_dbalias, $wsus_dbuser, $wsus_dbpass);
    if (!$msconnect) {
        $logger->logit("Cannot connect to WSUS server " . $conf->wsus_dbalias . ":" . mssql_get_last_message(), LOG_ERR);
        return false;
    }
    $db = mssql_select_db($conf->wsus_db, $msconnect);
    if (!$db) {
        $logger->logit("Couldn't open database " . $conf->wsus_db . " " . mssql_get_last_message(), LOG_ERR);
        return false;
    }
}

/**
 * Returns the hostname part of an fqdn thus everything before the first dot
 */
function get_hostname($fqdn) {
    global $logger;

    $dot_pos = strpos($fqdn, '.'); // find position of first dot
    $hostname;
    if ($dot_pos) {
        $hostname = substr($fqdn, 0, $dot_pos); // take everything before the first dot
    } else {
        $hostname = $fqdn;
    }

    $logger->debug("Converting $fqdn to $hostname", 2);

    return strtolower($hostname);
}

/**
 * Look up a wsus hostname in the vmps table and return the vmps id if and only if there's exactly one entry
 */
function get_vmps_id($hostname) {
    global $logger;

    $logger->debug("Looking for vmps system id for hostname $hostname", 1);

    $query = "select id from systems where substring_index(last_hostname, '.', 1) = '$hostname';";
    $logger->debug("Executing: $query", 3);
    $result = mysql_query($query);
    if (!$result) {
        $logger->logit("Could not obtain vmps id for $hostname, " . mysql_error(), LOG_WARNING);
        return false;
    }
    $num_rows = mysql_num_rows($result); //TODO: exception handling
    if ($num_rows == 0) {
        //$logger->logit("No vmps id for system $hostname found", LOG_WARNING);
        $logger->logit("No vmps id for system $hostname found"); // don't flag as warning until 100% right, its flooding logcheck
        return false;
    } elseif ($num_rows == 1) {
        $row = mysql_fetch_row($result); //TODO: exception handling
        $logger->debug("hostname $hostname has vmps id $row[0]", 1);
        return $row[0];
    } else {
        //$logger->logit("$hostname is not unique in vmps", LOG_WARNING);
        $logger->logit("$hostname is not unique in vmps"); // don't flag as warning until 100% right, its flooding logcheck
        return false;
    }
}

/**
 * Empty all wsus tables.
 */
function empty_tables() {
    global $logger;

    $logger->debug("Emptying wsus tables", 1);
    # As of MySQL 5.1.6, truncate requires the DROP privilege
    #if( !mysql_query('truncate table wsus_systems;') ) {
    if (!mysql_query('delete from wsus_systems;')) {
        $logger->logit("Could not empty wsus_systems, " . mysql_error(), LOG_ERR);
        return false;
    }
    #if( !mysql_query('truncate table wsus_neededUpdates;') ) {
    if (!mysql_query('delete from wsus_neededUpdates;')) {
        $logger->logit("Could not empty wsus_neededUpdates, " . mysql_error(), LOG_ERR);
        return false;
    }
    #if( !mysql_query('truncate table wsus_systemToUpdates;') ) {
    if (!mysql_query('delete from wsus_systemToUpdates;')) {
        $logger->logit("Could not empty wsus_systemToUpdate, " . mysql_error(), LOG_ERR);
        return false;
    }

    return true;
}

/**
 * Get global list of needed updates from wsus db and store in openac db
 */
function get_global_update_list() {
    global $logger, $timestamp;

    // A summarizationstate of 4 references updates that a properly installed. These are the only ones we are not interested in. Languageid 1033 is english, thus we only fetch the english descriptions for the udpates
    $query = "select distinct u.localupdateid, lp.title, lp.description, p.msrcseverity, p.creationdate, p.receivedfromcreatorservice from susdb.dbo.tbupdatestatuspercomputer us	left join susdb.dbo.tbupdate u on us.localupdateid = u.localupdateid left join dbo.tbrevision r on u.localupdateid = r.localupdateid	left join dbo.tbproperty p on r.revisionid = p.revisionid	left join dbo.tblocalizedpropertyforrevision lpr on r.revisionid = lpr.revisionid left join dbo.tblocalizedproperty lp on lpr.localizedpropertyid = lp.localizedpropertyid where (us.summarizationstate <> 4) and (us.summarizationstate <> 1) and lpr.languageid = 1033 and r.revisionid = (select max(r.revisionid) from tbrevision r where r.localupdateid = u.localupdateid)";

    $logger->debug("Executing: " . $query, 3);
    $result = mssql_query($query);
    if (!$result) {
        $logger->logit("Could not fetch global update list, " . mssql_get_last_message(), LOG_ERR);
        return false;
    }

    ;
    while ($row = mssql_fetch_assoc($result)) {
        $query = sprintf("insert into wsus_neededUpdates(localupdateid, title, description, msrcseverity, creationdate, receiveddate, lastsync) values('%s', '%s', '%s', '%s', '%s', '%s', '%s');", validate($row['localupdateid']), validate($row['title']), validate($row['description']), validate($row['msrcseverity']), convert_date($row['creationdate']), convert_date($row['receivedfromcreatorservice']), $timestamp);
        $logger->debug("Executing: $query", 3);

        if (!mysql_query($query)) {
            $logger->logit("Could insert update list into vmps db, " . mysql_error(), LOG_ERR);
            return false;
        }
    }

    return true;
}

/**
 * Obtain list of system which are registerd in the wsus server, fetch their status for each  necessary update and write everything into the vmps db
 */
function get_systems() {
    global $logger, $timestamp;

    $query = "select t.targetid, t.fulldomainname, t.ipaddress, t.lastreportedstatustime, s.notinstalled, s.downloaded, s.installedpendingreboot, s.failed, d.computermake, d.computermodel, o.oslongname from dbo.tbcomputertarget t left join dbo.tbcomputersummaryformicrosoftupdates s on t.targetid = s.targetid left join dbo.tbcomputertargetdetail d on t.targetid = d.targetid left join dbo.tbosmap o on (d.osminorversion = o.osminorversion and d.osmajorversion = o.osmajorversion and d.osservicepackmajornumber = o.osservicepackmajornumber) where (o.processorarchitecture is null or o.processorarchitecture = 1)";

    $logger->debug("Executing: $query", 3);
    $result = mssql_query($query);
    if (!$result) {
        $logger->logit("Failed to obtain systems from wsus, " . mssql_get_last_message(), LOG_ERR);
        return false;
    }

    while ($sys_row = mssql_fetch_assoc($result)) {
        $hostname = get_hostname($sys_row['fulldomainname']);
        $id = get_vmps_id($hostname);
        if (!$id) {
            continue;
        }


        $query = sprintf("select us.localupdateid from dbo.tbupdatestatuspercomputer us where (us.summarizationstate <> 4) and (us.summarizationstate <> 1) and us.targetid = '%s'", $sys_row['targetid']);
        $logger->debug("Executing: $query", 3);
        $result_update = mssql_query($query);
        if (!$result) { // whenever there occurs an error we skip the current system and continue with the next one
            $logger->logit("Could not fetch update details for " . $sys_row['fulldomainname'] . ", skipping this system", LOG_WARNING);
            continue;
        }
        // insert system into wsus_systems
        $query = sprintf("insert into wsus_systems values('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s');", $id, $hostname, validate($sys_row['ipaddress']), convert_date($sys_row['lastreportedstatustime']), validate($sys_row['oslongname']), validate($sys_row['computermake']), validate($sys_row['computermodel']), validate($sys_row['notinstalled']), validate($sys_row['downloaded']), validate($sys_row['installedpendingreboot']), validate($sys_row['failed']), $timestamp);

        $logger->debug("Executing: $query", 3);
        if (!mysql_query($query)) {
            $logger->logit("Could not insert system $hostname, " . mysql_error(), LOG_WARNING);
            continue;
        }

        // insert mapping to needed updates
        while ($update_row = mssql_fetch_assoc($result_update)) {
            $query = sprintf("insert into wsus_systemToUpdates(sid, localupdateid, lastsync) values('%s', '%s', '%s');", $id, validate($update_row['localupdateid']), $timestamp);
            $logger->debug("Executing $query", 3);
            if (!mysql_query($query)) {
                $logger->logit("Could not insert update relation $id, " . $update_row['localupdateid'], LOG_WARNING);
            }
        }
    }

    return true;
}

/**
 *
 */
function cleanup() {
    global $logger;

    // TODO: exception handling	

    mssql_close();
    mysql_close();

    $logger->logit("Done syncing WSUS");

    exit;
}
?>	
