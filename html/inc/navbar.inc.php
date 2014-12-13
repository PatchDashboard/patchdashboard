<?php
/*
 * Fail-safe check. Ensures that they go through the main page (and are authenticated to use this page
 */
if (!isset($index_check) || $index_check != "active"){
    exit();
}

$data = "";
foreach ($navbar_array as $key=>$val){
    $plugin2 = $key;
    $plugin2_glyph = $val["glyph"];
    $plugin_name = ucwords($plugin2);
            $data .= "<div class='panel panel-default'>
                    <div class='panel-heading'>
                        <h4 class='panel-title'>
                            <a data-toggle='collapse' data-parent='#accordion' href='#collapse$plugin2'><span class='glyphicon $plugin2_glyph'>
                            &nbsp;&nbsp;</span>$plugin_name</a>
                        </h4>
                    </div>
                    <div id='collapse$plugin2' class='panel-collapse collapse in'>
                        <div class='panel-body'>
                            <table class='table'>";
    foreach ($val['page_and_glyph'] as $val2){
        $tmp_array = explode(",",$val2);
        $page_string = $tmp_array[0];
        $page_words = ucwords(str_replace("_"," ",$page_string));
        if (isset($tmp_array[1])){
            $page_glyph = "<span class=\"glyphicon ".$tmp_array[1]." text-primary\"></span>&nbsp;&nbsp;";
        }
        else{
            $page_glyph = "";
        }
        /*
         * Badge code:
         * <span class=\"badge\">42</span>
         * TODO: work the badge code in dynamically with patche count
         */
        $data .= "                                <tr>
                                    <td>
                                        $page_glyph<a href=\"$page_string\">$page_words</a>
                                    </td>
                                </tr>";
    }
        $data .= "</table>
                        </div>
                    </div>
                </div>";
}
if (!isset($_SESSION['error_notice'])){
    $error_html = "";
}
else{
    $error_message = $_SESSION['error_notice'];
    $error_html = "<div class='alert alert-error'>
        <a href='#' class='close' data-dismiss='alert'>&times;</a>
        <strong>Error! </strong> $error_message
    </div>";
    unset($_SESSION['error_notice']);
    unset($error_message);
}

if (!isset($_SESSION['good_notice'])){
    $good_html = "";
}
else{
    $good_message = $_SESSION['good_notice'];
    $good_html = "<div class='alert alert-success'>
        <a href='#' class='close' data-dismiss='alert'>&times;</a>
        <strong>Notice: </strong> $good_message
    </div>";
    unset($_SESSION['good_notice']);
    unset($good_message);
}

if (!isset($_SESSION['warning_notice'])){
    $warning_html = "";
}
else{
    $warning_message = $_SESSION['warning_notice'];
    $warning_html = "<div class='alert alert-warning'>
        <a href='#' class='close' data-dismiss='alert'>&times;</a>
        <strong>Warning: </strong> $warning_message
    </div>";
    unset($_SESSION['warning_notice']);
    unset($warning_message);
}
$all_messages_to_send = "${warning_html}${good_html}${error_html}";
?>
    <div class="container-fluid">
        <?php echo $all_messages_to_send;unset($all_messages_to_send);?>
      <div class="row">
        <div class="col-sm-3 col-md-3">
            <div class="panel-group" id="accordion">
                <?php echo $data;?>
            </div>
        </div>


