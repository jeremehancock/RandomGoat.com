<?php
// Simple embed.php file to iframe index.html full screen
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Embedded Content</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        html, body {
            height: 100%;
            overflow: hidden;
        }
        
        iframe {
            width: 100%;
            height: 100%;
            border: none;
            display: block;
        }
    </style>
</head>
<body>
    <iframe src="index.html" frameborder="0" allowfullscreen></iframe>
    
    <!-- Default Statcounter code for Random Goat v2 Embed https://randomgoat.com/embed.php -->
	<script type="text/javascript">
	var sc_project=13146733; 
	var sc_invisible=1; 
	var sc_security="2b8ffaa1"; 
	</script>
	<script type="text/javascript"
	src="https://www.statcounter.com/counter/counter.js"
	async></script>
	<noscript><div class="statcounter"><a title="Web Analytics
	Made Easy - Statcounter" href="https://statcounter.com/"
	target="_blank"><img class="statcounter"
	src="https://c.statcounter.com/13146733/0/2b8ffaa1/1/"
	alt="Web Analytics Made Easy - Statcounter"
	referrerPolicy="no-referrer-when-downgrade"></a></div></noscript>
	<!-- End of Statcounter Code -->
	
</body>
</html>
