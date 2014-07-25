&copy; 2014 YOUR NAME
</div>
<div id="footer"></div>
    </div>

    <!-- Bootstrap core JavaScript
    ================================================== -->
    <!-- Placed at the end of the document so the pages load faster -->
    <script src="/js/jquery.min.js"></script>
    <script src="/js/bootstrap.min.js"></script>
    <script src="/js/docs.min.js"></script>
    <script src="/js/jquery.easy-pie-chart.js"></script>
    <script src="/js/excanvas.js"></script>
            <script type="text/javascript">
		function NewURL(val){
			base = '/search/'
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
