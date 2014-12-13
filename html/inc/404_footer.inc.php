<?php
/*
 * Fail-safe check. Ensures that they go through the main page (and are authenticated to use this page
 */
if (!isset($index_check) || $index_check != "active"){
    exit();
}
?>
</div>
	  <div id="footer" align="center">&&copy; 2014 <?php echo YOUR_COMPANY;?></div>
    </div>
    <script src="<?php echo BASE_PATH; ?>js/jquery.min.js"></script>
    <script src="<?php echo BASE_PATH; ?>js/bootstrap.min.js"></script>
  </body>
</html>

