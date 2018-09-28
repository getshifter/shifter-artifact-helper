<?php // custom WordPress database error page

  header('HTTP/1.1 503 Service Temporarily Unavailable');
  header('Status: 503 Service Temporarily Unavailable');
  header('Retry-After: 600'); // 1 hour = 3600 seconds

?>

<!DOCTYPE HTML>
<html>
  <head>
  <title>Database Error</title>
  <style>
    body { width: 100%; height: 100%; font-family: "HelveticaNeue-Light", "Helvetica Neue Light", "Helvetica Neue", Helvetica, Arial, "Lucida Grande", sans-serif; background: #eee; color: #282829; font-size: 18px; }
    .centered { display: table; width: 274px; height: 274px; padding: 20px; border: 1px solid #707070; text-align: center; margin: 150px auto; margin-top: 25vh;}
    .centered-content { display: table-cell; vertical-align: middle; }
    h1 { margin: 0 auto 12px; font-size: 20px; }
    p { margin: 0; }
  </style>
  </head>
<body>
  <div class="centered">
    <div class="centered-content">
      <h1>Pardon the Interruption</h1>
      <p>There seems to be a database error. Please check back soon.</p>
    </div>
  </div>
</body>
</html>