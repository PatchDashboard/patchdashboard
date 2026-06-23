<?php

/**
 * PHP version 5
 *
 * LICENSE: This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as published
 * by the Free Software Foundation.
 *
 * @package                     FreeNAC
 * @author                      Hector Ortiz (FreeNAC Core Team)
 * @copyright                   2006 FreeNAC
 * @license                     http://www.gnu.org/copyleft/gpl.html   GNU Public License Version 2
 * @version                     CVS: $Id:$
 * @link                        http://www.freenac.net
 *
 */
/**
 * Retrieves information from the WSUS database and stored in local mysql
 * table.
 * Beta test with Wsus V2.???
 * Stable release v2.2 testted with Wusus version 3.???
 */

/**
 * Prints script's usage
 */
function usage() {
    $logger->logit("Usage: wsus_getinfo [-h][-v[v]][-s]\n");
    $logger->logit("\t-h\tShow this help screen\n");
    $logger->logit("\t-v\tDebug level 1 enabled (output goes to stdout & syslog)\n");
    $logger->logit("\t-vv\tDebug level 1 & 2 enabled (output goes to stdout & syslog)\n");
    $logger->logit("\t-s\tSupress messages to standard output and redirect them to syslog\n");
    exit(1);
}

/**
 * Connect to the WSUS server
 */
function dbwsus_connect() {
    global $conf, $wsus_dbuser, $wsus_dbpass;
    message("Connect to " . $conf->wsus_dbalias . " " . $conf->wsus_db, 1);
    $msconnect = mssql_connect($conf->wsus_dbalias, $wsus_dbuser, $wsus_dbpass);
    if (!$msconnect) {
        message("Cannot connect to WSUS server " . $conf->wsus_dbalias . ":" . mssql_get_last_message(), 0);
        return false;
    }
    $d = mssql_select_db($conf->wsus_db, $msconnect);
    if (!$d) {
        message("Couldn't open database " . $conf->wsus_db . " " . mssql_get_last_message(), 0);
        return false;
    }
    return true;
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
 * Executes query and displays error message if any
 */
function execute_query($query) {
    db_connect();
    $res = mysql_query($query);
    if (!$res) {
        message("Cannot execute query $query because " . mysql_error(), 2);
        return false;
    }
    return $res;
}

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
 * Dumps the tbComputerTarget table into our computertarget tabl
 */
function wsus_dump_computertarget() {
    message("Function wsus_dump_computertarget", 1);
    db_connect();
    if (dbwsus_connect()) {
        #This query is for WSUS 2.X
        #$query="select TargetID,IPAddress,FullDomainName,OSMajorVersion,OSMinorVersion,OSBuildNumber,OSServicePackMajorNumber,OSServicePackMinorNumber,OSLocale,ComputerMake,ComputerModel,BiosVersion,BiosName,BiosReleaseDate,ProcessorArchitecture from tbComputerTarget";
        #This query is for WSUS 3.0
        $query = "select ct.TargetID, ct.IPAddress, ct.FullDomainName, td.OSMajorVersion, td.OSMinorVersion, td.OSBuildNumber, td.OSServicePackMajorNumber, td.OSServicePackMinorNumber, td.OSLocale, td.ComputerMake, td.ComputerModel, td.BiosVersion, td.BiosName, td.BiosReleaseDate, td.ProcessorArchitecture from tbComputerTarget as ct, tbcomputertargetdetail as td where ct.targetID=td.targetID";
        message("Executing: " . $query, 2);
        $res = mssql_fetch_all($query);
        foreach ($res as $row) {
            $old_date = $row[BiosReleaseDate];
            $temp = explode('.', $row[FullDomainName]);
            $row[FullDomainName] = $temp[0];
            $row[BiosReleaseDate] = convert_date($row[BiosReleaseDate]);
            message("Converted $old_date into " . $row[BiosReleaseDate] . " for " . $row[FullDomainName], 1);
            $row[OSLocale] = substr($row[OSLocale], 0, 2);
            $osid = wsus_get_osid($row[OSMajorVersion], $row[OSMinorVersion], $row[OSBuildNumber], $row[OSServicePackMajorNumber], $row[OSServicePackMinorNumber], $row[ProcessorArchitecture]);
            $query = sprintf("insert into nac_wsuscomputertarget (TargetID, IPAddress, FullDomainName, OSid, OSLocale, ComputerMake, ComputerModel, BiosVersion, BiosName, BiosReleaseDate) values ('%s','%s','%s','%s','%s','%s','%s','%s','%s','%s') on duplicate key update datetime=NOW();", validate($row[TargetID]), validate($row[IPAddress]), validate($row[FullDomainName]), validate($osid), validate($row[OSLocale]), validate($row[ComputerMake]), validate($row[ComputerModel]), validate($row[BiosVersion]), validate($row[BiosName]), validate($row[BiosReleaseDate]));
            message("Executing: " . $query, 2);
            execute_query($query);
        }
        return true;
    } else
        return false;
}

/**
 * Dumps table tbOSMap into our osmap table
 */
function wsus_dump_osmap() {
    message("Function wsus_dump_osmap", 1);
    if (dbwsus_connect()) {
        $query = "select * from tbOSMap";
        message("Executing: " . $query, 2);
        $res = mssql_fetch_all($query);
        db_connect();
        $query = 'delete from nac_wsusosmap'; //Delete everything we previosuly had.
        message("Executing: " . $query, 2);
        execute_query($query);
        foreach ($res as $row) {
            $query = sprintf("insert into nac_wsusosmap values ('%s','%s','%s','%s','%s','%s','%s','%s','%s');", $row[OSid], $row[OSMajorVersion], $row[OSMinorVersion], $row[OSBuildNumber], $row[OSServicePackMajorNumber], $row[OSServicePackMinorNumber], $row[ProcessorArchitecture], validate($row[OSShortName]), validate($row[OSLongName]));
            message("Executing: " . $query, 2);
            execute_query($query);
        }
        return true;
    } else
        return false;
}

/**
 * Get the OSID
 */
function wsus_get_osid($majv, $minv, $build, $spmaj, $spmin, $processor) {
    message("Function wsus_getosid", 1);
    db_connect();
    $query = "select pid from nac_wsusprocessor where ProcessorArchitecture='$processor';";
    message("Executing: " . $query, 2);
    $my_res = execute_query($query);
    if ($my_res) {
        $my_result = mysql_fetch_array($my_res, MYSQL_ASSOC);
        if (dbwsus_connect()) {
            $query = sprintf("select osid from tbosmap where osmajorversion='%s' and osminorversion='%s' and osbuildnumber='%s' and osservicepackmajornumber='%s' and osservicepackminornumber='%s' and processorarchitecture='%s'", $majv, $minv, $build, $spmaj, $spmin, $my_result['pid']);
            message("Executing: " . $query, 2);
            $ms_res = mssql_fetch_all($query);
            if ($ms_res) {
                foreach ($ms_res as $row) {
                    $result = $row[osid];
                }
            }
        }
        return $result;
    } else
        return false;
}

/**
 * Retrieves the installed and needed updates for computer identified by id
 */
function wsus_get_updates_per_computer($id) {
    message("Function wsus_get_updates_per_computer for $id", 1);
    if (dbwsus_connect()) {
        #Query for WSUS 2.X
        #$query="select SummarizationState, UpdateID, LastChangeTime, LastRefreshTime from tbUpdateStatusPerComputer where TargetID='$id' and SummarizationState=4 order by id asc"; #Installed updates
        #Query for WSUS 3.0
        $query = "select SummarizationState, LocalUpdateID, LastChangeTime, LastRefreshTime from tbUpdateStatusPerComputer where TargetID='$id' and SummarizationState=4 order by LocalUpdateID asc"; #Installed updates
        message("Executing: " . $query, 2);
        $installed = mssql_fetch_all($query);
        if ($installed) {
            db_connect();
            $query = "delete from nac_wsusupdatestatuspercomputer where TargetID='$id' and SummarizationState='4';";
            message("Executing: " . $query, 2);
            execute_query($query);
            foreach ($installed as $row) {
                #Query for WSUS 2.X
                #$query=sprintf("insert into nac_wsusupdatestatuspercomputer values('%s','%s','%s','%s','%s');",validate($row[SummarizationState]),validate(mssql_guid_string($row[UpdateID])),validate($id),validate(convert_date($row[LastChangeTime])),validate(convert_date($row[LastRefreshTime])));
                #Query for WSUS 3.0
                $query = sprintf("insert into nac_wsusupdatestatuspercomputer values('%s','%s','%s','%s','%s');", validate($row[SummarizationState]), validate($row[LocalUpdateID]), validate($id), validate(convert_date($row[LastChangeTime])), validate(convert_date($row[LastRefreshTime])));
                message("Executing: " . $query, 2);
                execute_query($query);
            }
        }
        #Query for WSUS 2.x
        #$query="select SummarizationState, UpdateID, LastChangeTime, LastRefreshTime from tbUpdateStatusPerComputer where TargetID='$id' and SummarizationState='6' order by id asc";#Needed updates
        #Query for WSUS 3.0
        $query = "select SummarizationState, LocalUpdateID, LastChangeTime, LastRefreshTime from tbUpdateStatusPerComputer where TargetID='$id' and SummarizationState=4 order by LocalUpdateID asc"; #Installed updates
        message("Executing: " . $query, 2);
        $needed = mssql_fetch_all($query);
        if ($needed) {
            db_connect();
            $query = "delete from nac_wsusupdatestatuspercomputer where targetID='$id' and SummarizationState='6';";
            message("Executing: " . $query, 2);
            execute_query($query);
            $i = 0;
            foreach ($needed as $row) {
                #Query for WSUS 2.X
                #$query=sprintf("insert into nac_wsusupdatestatuspercomputer values('%s','%s','%s','%s','%s');",validate($row[SummarizationState]),validate(mssql_guid_string($row[UpdateID])),validate($id),validate(convert_date($row[LastChangeTime])),validate(convert_date($row[LastRefreshTime])));
                #Query for WSUS 3.0
                $query = sprintf("insert into nac_wsusupdatestatuspercomputer values('%s','%s','%s','%s','%s');", validate($row[SummarizationState]), validate($row[LocalUpdateID]), validate($id), validate(convert_date($row[LastChangeTime])), validate(convert_date($row[LastRefreshTime])));
                message("Executing: " . $query, 2);
                execute_query($query);
            }
        }
        #Query for WSUS 3.0
        $query = "select LastSyncTime  from tbcomputertarget where TargetID='$id';";
        message("Executing: " . $query, 2);
        $result = mssql_fetch_all($query);
        if ($result) {
            foreach ($result as $row) {
                $lastsynctime = validate(convert_date($row[LastSyncTime]));
                $query = "update nac_wsuscomputertarget set LastSyncTime='$lastsynctime' where targetid='$id';";
                message("Executing: " . $query, 2);
                execute_query($query);
            }
        }
        return true;
    } else
        return false;
}

/**
 * This one retrieves the installed and needed updates for every computer
 */
function wsus_dump_updates_for_computers() {
    message("Function wsus_dump_updates_for_computers", 1);
    db_connect();
    $query = "select TargetID from nac_wsuscomputertarget;";
    message("Executing: " . $query, 2);
    $my_res = execute_query($query);
    if ($my_res) {
        while ($row = mysql_fetch_array($my_res, MYSQL_ASSOC)) {
            wsus_get_updates_per_computer($row['TargetID']);
        }
        return true;
    }
    return false;
}

/**
 * Connect data from WSUS with the data stored in the systems table
 */
function connect_data() {
    db_connect();
    $query = "select id,name from systems";
    message("Executing: " . $query, 2);
    $my_res = execute_query($query);
    if ($my_res) {
        while ($row = mysql_fetch_array($my_res, MYSQL_ASSOC)) {
            if ((!empty($row['name'])) && (strcasecmp($row['name'], 'unknown') != 0)) {
                $query = "update nac_wsuscomputertarget set sid=" . $row['id'] . " where FullDomainName like '" . $row['name'] . "'";
                message("Executing: " . $query, 2);
                execute_query($query);
                if (mysql_affected_rows() == 1)
                    message("Updated patch information for host {$row['name']}", 0);
                else if (mysql_affected_rows() > 1)
                    message("Possible duplicates in database for host {$row['name']}", 0);
            } else
                continue;
        }
        return true;
    } else
        return false;
}

/**
 * Get a list of updates available on the server
 */
function wsus_dump_updates() {
    global $wsus_language;
    message("Function wsus_dump_updates", 1);
    if (dbwsus_connect() && $wsus_language) {
        $query = "select u.localupdateid as LocalID,u.updateid as UpdateID,pre.title as Title,kb.kbarticleid as Article from tbprecomputedlocalizedproperty pre, tbkbarticleforrevision kb, tbupdate u where pre.updateid=u.updateid and pre.revisionid=kb.revisionid and pre.shortlanguage='$wsus_language' order by (kb.revisionid) asc";
        message("Executing: " . $query, 2);
        $ms_res = mssql_fetch_all($query);
        if ($ms_res) {
            db_connect();
            /* $query='delete from nac_wsusupdate;';
              message("Executing: ".$query,2);
              execute_query($query); */
            foreach ($ms_res as $row) {
                $updateid = mssql_guid_string($row[UpdateID]);
                $query = sprintf("insert into nac_wsusupdate values('%s','%s','%s','%s') on duplicate key update UpdateID='%s';", validate($row[LocalID]), validate($updateid), validate($row[Title]), validate($row[Article]), validate($updateid));
                message("Executing: " . $query, 2);
                execute_query($query);
            }
            return true;
        } else
            return false;
    } else
        return false;
}

/**
 * Print a message
 */
function message($string, $level) {
    global $output, $logger;

    if ($level == 0) {
        if ($output) {
            $logger->setLogToStdOut();
            $logger->logit("$string");
        } else {
            $logger->setLogToStdOut(false);
            $logger->logit($string);
        }
    }
    if ($level == 1) {
        if ($logger->getDebugLevel()) {
            $logger->debug($string, 1);
            if ($output) {
                $logger->setLogToStdOut();
                $logger->logit("$string");
            }
        }
    }
    if ($level == 2) {
        if ($logger->getDebugLevel()) {
            $logger->debug($string, 2);
            if ($output) {
                $logger->setLogToStdOut();
                $logger->logit("$string");
            }
        }
    }
    return true;
}

?>
