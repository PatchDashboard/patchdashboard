<?php
/*
 * Fail-safe check. Ensures that they go through the main page (and are authenticated to use this page
 */
if (!isset($index_check) || $index_check != "active"){
    exit();
}
?>
          <h1 class="page-header">4 (D)OH 4</h1>
        <div class="container">
              <div class="row">
        <div class="col-md-12">
            <div class="error-template">
                <h1>
                    Coming Soon</h1>
                <h2>
                    Almost done!</h2>
                <div class="error-details">
                    Sorry, but this section is still under construction.
                </div>
                <div class="error-actions">
                    <a href="<?php echo BASE_PATH;?>" class="btn btn-primary btn-lg"><span class="glyphicon glyphicon-home"></span>Take Me Home </a>
                </div>
            </div>
        </div>
    </div>
        </div>