<?php
/*
 * Fail-safe check. Ensures that they go through the main page (and are authenticated to use this page
 */
if (!isset($index_check) || $index_check != "active"){
    exit();
}
?>
      </div>
      <div id="footer" align="center">&copy; 2014 <?php echo YOUR_COMPANY;?> || Powered by <a href="http://patchdashboard.com" target="_blank">PatchDashboard</a> || Fork on <a href="https://github.com/jonsjava/patchdashboard" target="_blank">GitHub</a></div>
    </div>
    <script src="<?php echo BASE_PATH; ?>js/jquery.min.js"></script>
    <script src="<?php echo BASE_PATH; ?>js/bootstrap.min.js"></script>
    <script src="<?php echo BASE_PATH; ?>js/docs.min.js"></script>
    <script src="<?php echo BASE_PATH; ?>js/jquery.easy-pie-chart.js"></script>
    <script src="<?php echo BASE_PATH; ?>js/excanvas.js"></script>
    <script src="<?php echo BASE_PATH; ?>js/jquery.tablesorter.js"></script>
    <script src="<?php echo BASE_PATH; ?>js/jquery.metadata.js"></script>

            <script type="text/javascript">
		$(function() {
            		$("table#server_list").tablesorter({ sortList: [[1,1]] });
        	});
                function NewURL(val){
                        base = '<?php echo BASE_PATH;?>search/';
                        window.location.assign(base + val);
                }
            var initPieChart = function() {
                $('.percentage').easyPieChart({
                    animate: 1000
                });
                $('.percentage-light').easyPieChart({
                    barColor: function(percent) {
                        percent /= 100;
                        return "rgb(" + Math.round(255 * (1-percent)) + ", " + Math.round(255 * percent) + ", 0)";
                    },
                    trackColor: '#666',
                    scaleColor: false,
                    lineCap: 'butt',
                    lineWidth: 15,
                    animate: 1000
                });

                $('.updateEasyPieChart').on('click', function(e) {
                  e.preventDefault();
                  $('.percentage, .percentage-light').each(function() {
                    var newValue = Math.round(100*Math.random());
                    $(this).data('easyPieChart').update(newValue);
                    $('span', this).text(newValue);
                  });
                });
            };


        </script>
  </body>
</html>
