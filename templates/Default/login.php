<!DOCTYPE html>
<html lang="<?php print $this->langCode ?>">
	<head>
		<base href="<?php print BASE_HREF ?>" />
	    <meta charset="utf-8">
	    <meta name="viewport" content="width=device-width, initial-scale=1.0">
	    <title><?php print $this->pageTitle ?></title>
	    <?php print $this->pageStyles ?>
	    <link rel="stylesheet" href="<?php print $this->templatePath ?>css/bootstrap.css">
	    <link rel="stylesheet" href="<?php print $this->templatePath ?>css/bootstrap-theme.css">
	    <link rel="stylesheet" href="<?php print $this->templatePath ?>css/toastr.css">
	    <link rel="stylesheet" href="<?php print $this->templatePath ?>css/custom.css">
	    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
	</head>
	<body>
	    <div id="wrapper">
	        <div id="page-content-wrapper">
				<div class="container-fluid">
					<div id="messageArea"></div>
					<div class="row">
						<?php print $this->pageContent ?>
					</div>
				</div>
	        </div>
	    </div>
		<?php print $this->pageScripts ?>
		<script src="<?php print $this->templatePath ?>js/jquery.min.js" type="text/javascript"></script>
		<script src="<?php print $this->templatePath ?>js/jquery.cookie.js" type="text/javascript"></script>
		<script src="<?php print $this->templatePath ?>js/bootstrap.js" type="text/javascript"></script>
		<script src="<?php print $this->templatePath ?>js/toastr.js" type="text/javascript"></script>
		<script src="<?php print $this->templatePath ?>js/custom.js" type="text/javascript"></script>
	</body>    
</html>