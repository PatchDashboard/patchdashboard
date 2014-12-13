<?php
/*
 * Fail-safe check. Ensures that they go through the main page (and are authenticated to use this page
 */
if (!isset($index_check) || $index_check != "active"){
    exit();
}
?>
        <div class="col-sm-9 col-md-9">
            <h3 class="text-center login-title">Add User</h1>
            <div class="account-wall">
                <form id ="addUser" method="POST" action="<?php echo BASE_PATH;?>plugins/admin/p_add_user.inc.php"
		        data-bv-message="This value is not valid"
      			data-bv-feedbackicons-valid="glyphicon glyphicon-ok"
			data-bv-feedbackicons-invalid="glyphicon glyphicon-remove"
			data-bv-feedbackicons-validating="glyphicon glyphicon-refresh">
                    <div class="form-group"><label class="col-sm-5 control-label">Username</label><div class="col-sm-5"><input type="text" name="username" class="form-control" placeholder="Username" required autofocus ></div></div>
                    <div class="form-group"><label class="col-sm-5 control-label">Display Name</label><div class="col-sm-5"><input type="text" name="display_name" class="form-control" placeholder="Nickname/Real Name" required autofocus ></div></div>
                    <div class="form-group"><label class="col-sm-5 control-label">Password</label><div class="col-sm-5"><input type="password" name="password" class="form-control" placeholder="Password" required 
				   data-bv-notempty="true" data-bv-notempty-message="The password is required and cannot be empty"
		                   data-bv-identical="true" data-bv-identical-field="confirmPassword" data-bv-identical-message="The password and its confirm are not the same"
                		   data-bv-different="true" data-bv-different-field="username" data-bv-different-message="The password cannot be the same as username" /></div></div>
                    <div class="form-group"><label class="col-sm-5 control-label">Confirm Password</label><div class="col-sm-5"><input type="password" name="confirmPassword" class="form-control" placeholder="Retype Password" required
				   data-bv-notempty="true" data-bv-notempty-message="The confirm password is required and cannot be empty"
	                	   data-bv-identical="true" data-bv-identical-field="password" data-bv-identical-message="The password and its confirm are not the same"
	        	           data-bv-different="true" data-bv-different-field="username" data-bv-different-message="The password cannot be the same as username" /></div></div>
                    <div class="form-group"><label class="col-sm-5 control-label">E-Mail Address</label><div class="col-sm-5"><input type="text" name="email" class="form-control" placeholder="E-mail Address" required ></div></div>
                    <div class="form-group"><label class="col-sm-5 control-label">Are they an Admin?</label><div class="col-sm-5"><input type="checkbox" name="is_admin" class="form-control"> </div></div>
                    <div class="form-group"><label class="col-sm-5 control-label">Receive Alerts?</label><div class="col-sm-5"><input type="checkbox" name="alerts" class="form-control"></div></div>
                    <div class="form-group"><label class="col-sm-5 control-label"></label><div class="col-sm-5"><button class="btn btn-lg btn-primary btn-block" type="submit">Add User</button></div></div>
                    <div class="form-group"><label class="col-sm-5 control-label"></label><div class="col-sm-5"><label class="checkbox pull-left"></label></div></div>
                </form>
            </div>
        </div>
