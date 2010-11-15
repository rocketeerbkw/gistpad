<?php

/*
Copyright (c) 2010 Brandon Williams

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.
*/
    
require('simplediff/class.simplediff.php');

$gistId = null;
$gistUrl = null;
$err = null;
$gistUrlRegex = '#gist.github.com\/(\d+)#';

$revisions = array();
$curRevSource = array();
$prevRevSource = array();
$gistSource = array();

$codePadData = array('lang' => 'PHP', 'private' => 'True', 'run' => 'True', 'submit' => 'Submit');

if (isset($_REQUEST['gist'])) {
  
  /* Form and data validation
  ------------------------------------------------------------------------------- */
  $gistId = filter_var($_REQUEST['gist'], FILTER_VALIDATE_INT);
  $gistUrl = filter_var($_REQUEST['gist'], FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => $gistUrlRegex)));
  
  if ($gistId || $gistUrl) {
    if ($gistUrl) {
      preg_match($gistUrlRegex, $gistUrl, $matches);
      $gistId = $matches[1];
    }
  } else {
    $err = 'Please enter a valid gist (ID or URL).';
  }
  
  if ($err) {
    echo $err;
  } else {
    
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && $_POST['go'] == 'Go!') {
      header('Location: ' . $gistId);
    }
  
    // Get the gist page source
    $page = curl('https://gist.github.com/' . $gistId);
    
    // Get list of revisions
    preg_match_all('#<a href="https:\/\/gist.github.com\/\d+\/(\w+)" class="id">([^<]+)</a>#', $page, $matches);
    foreach ($matches[2] as $index => $rev) {
      $revisions[$index]['short'] = $rev;
      $revisions[$index]['sha1'] = $matches[1][$index];
    }
    
    // Get list of files for all revisions
    foreach ($revisions as $rev => $data) {
      $page = curl('https://gist.github.com/' . $gistId . '/' . $data['sha1']);
      
      preg_match_all('#<a href="\/raw\/\d+\/(\w+)\/([^"]+)">raw</a>#', $page, $matches);
      //var_dump($matches);
      $files = array();
      foreach ($matches[2] as $index => $file) {
        $files[$file] = 'https://gist.github.com/raw/' . $gistId . '/' . $matches[1][$index] . '/' . $file;
      }
      
      $revisions[$rev]['files'] = $files;
    }
    
    //echo '<pre>' . print_r($revisions, true) . '</pre>';
    
    // Determine the active revision
    $curRev = count($revisions) - 1;
    if (isset($_GET['rev'])) {
      foreach ($revisions as $index => $data) {
        if ($data['sha1'] == $_GET['rev']) {
          $curRev = $index;
          break;
        }
      }
    }
    
    // Get the source for the active revision
    foreach ($revisions[$curRev]['files'] as $file => $url) {
      $gistSource[$file]['cur'] = curl($url);
      
      // Get the source for the previous revision (for diffing)
      $gistSource[$file]['prev'] = '';
      if ($curRev < (count($revisions) - 1)) {
        $gistSource[$file]['prev'] = curl($revisions[$curRev+1]['files'][$file]);
      }
      
      // Diff the two
      $diff = simpleDiff::diff($gistSource[$file]['prev'], $gistSource[$file]['cur']);
      $gistSource[$file]['diff'] = html_diff($gistSource[$file]['prev'], $gistSource[$file]['cur']);
    }
    
    //print_r($gistSource);
    //echo '<pre>' . print_r($gistSource, true) . '</pre>';
    
  }
}

// Make sure there is no whitespace before first HTML code
// so we don't trigger Quirks mode
?><!DOCTYPE html>
<html>
  <head>
    <title>GistPad</title>
    
    <!--
    Copyright (c) 2010 Brandon Williams
    
    Permission is hereby granted, free of charge, to any person obtaining a copy
    of this software and associated documentation files (the "Software"), to deal
    in the Software without restriction, including without limitation the rights
    to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
    copies of the Software, and to permit persons to whom the Software is
    furnished to do so, subject to the following conditions:
    
    The above copyright notice and this permission notice shall be included in
    all copies or substantial portions of the Software.
    
    THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
    IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
    FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
    AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
    LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
    OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
    THE SOFTWARE.
    -->
    
    <style type="text/css">
.ins { background: #cfc; }
.del { background: #fcc; }
ins { background: #9f9; }
del { background: #f99; }
hr { background: none; border: none; border-top: 2px dotted #000; color: #fff; }
</style>
  </head>
  <body>
    <form method="post">
      <label for="gist">Gist:</label> <input type="text" name="gist" id="gist" value="<?= $gistId ? $gistId : $_REQUEST['gist'] ?>" /> <input type="submit" name="go" id="go" value="Go!" />
    </form>
    
    <!--
      REVISIONS
    -->
    
    <?php if (count($revisions) > 0): ?>
    <ul id="revisions">
      <?php foreach($revisions as $index => $data): ?>
        <li><a href="/gistpad/<?= $gistId ?>/<?= $data['sha1'] ?>"><?= $data['short'] ?></a></li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
    
    <table width="100%">
    <?php foreach ($gistSource as $fileName => $src): ?>
      <tr>
        <td colspan="2">
          <h2>
            <?= $fileName ?>
          </h2>
        </td>
      </tr>
      <tr>
        <td>
          <?php if ($src['prev'] !== ''): ?>
            Diff between <?= $revisions[$curRev]['short'] ?> and <?= $revisions[$curRev+1]['short'] ?>
          <?php else: ?>
          Showing oldest revision
          <?php endif; ?>
        </td>
        <td>
          Output
        </td>
      </tr>
      <tr>
        <td width="50%" valign="top">
          <!--
            SOURCE
          -->
          
          <?php if ($src['prev'] !== ''): ?>
            <pre><?= $src['diff'] ?></pre>
          <?php else: ?>
            <pre><?= htmlentities($src['cur']) ?></pre>
          <?php endif; ?>
        </td>
        <td valign="top">
          <!--
            CODEPAD
          -->
          <pre><?= getCodePad($src['cur']) ?></pre>
        </td>
      </tr>
    <?php endforeach; ?>
    </table>
    <p><a href="http://github.com/rocketeerbkw/gistpad">Source Code Available!</a></p>   
  </body>
</html>

<?php

function getCodePad($code, $lang = 'PHP') {
  $data = array('lang' => $lang, 'private' => 'True', 'run' => 'True', 'submit' => 'Submit', 'code' => $code);
  $response = curl('http://codepad.org', $data);
  
  // Get the short url for this code
  preg_match('#href="([^"]+)"#', $response, $matches);
  $url = $matches[1];
  
  $codePad = curl($url);
  
  // TODO use DOM instead of regex
  preg_match('#output-line-1.*?<pre>(.*?)</pre>#s', $codePad, $matches);
  $output = $matches[1];
  
  return $output;
}

function curl($url, $payload = null) {
  // create curl resource
  $ch = curl_init();
  
  // set curl options
  $curlOpts = array();
  $curlOpts[CURLOPT_URL] = $url;
  // Make sure we return response, instead of printing it out directly
  $curlOpts[CURLOPT_RETURNTRANSFER] = 1;
  // This can be anything, but something meaningful would be nice
  $curlOpts[CURLOPT_USERAGENT] = "PHP Curl request by GistPad rocketeerbkw@gmail.com";
  // SSL stuff
  $curlOpts[CURLOPT_SSL_VERIFYPEER] = 0;
  $curlOpts[CURLOPT_SSL_VERIFYHOST] = 0;
  
  //Tell curl to proxy connection
  //$curlOpts[CURLOPT_PROXY] = '127.0.0.1';
  //$curlOpts[CURLOPT_PROXYPORT] = 8888;
  
  // Extra options when POSTing
  if ($payload) {
    $curlOpts[CURLOPT_POST] = TRUE;
    $curlOpts[CURLOPT_POSTFIELDS] = $payload;
  }
  
  curl_setopt_array($ch, $curlOpts);
  
  $response = curl_exec($ch);
  
  // Check for errors
  if(curl_errno($ch)) {
    curl_close($ch);
    return false;
    //echo 'Curl error: ' . curl_error($ch) . PHP_EOL;
  }
  
  // close curl resource to free up system resources
  curl_close($ch);
  
  return $response;
}

function html_diff($old, $new)
{
    $diff = simpleDiff::diff_to_array(false, $old, $new);

    $out = '<table class="diff">';
    $prev = key($diff);

    foreach ($diff as $i=>$line)
    {
        if ($i > $prev + 1)
        {
            $out .= '<tr><td colspan="5" class="separator"><hr /></td></tr>';
        }

        list($type, $old, $new) = $line;

        $class1 = $class2 = '';
        $t1 = $t2 = '';

        if ($type == simpleDiff::INS)
        {
            $class2 = 'ins';
            $t2 = '+';
        }
        elseif ($type == simpleDiff::DEL)
        {
            $class1 = 'del';
            $t1 = '-';
        }
        elseif ($type == simpleDiff::CHANGED)
        {
            $class1 = 'del';
            $class2 = 'ins';
            $t1 = '-';
            $t2 = '+';

            $lineDiff = simpleDiff::wdiff($old, $new);

            // Don't show new things in deleted line
            $old = preg_replace('!\{\+(?:.*)\+\}!U', '', $lineDiff);
            $old = str_replace('  ', ' ', $old);
            $old = str_replace('-] [-', ' ', $old);
            $old = preg_replace('!\[-(.*)-\]!U', '<del>\\1</del>', $old);

            // Don't show old things in added line
            $new = preg_replace('!\[-(?:.*)-\]!U', '', $lineDiff);
            $new = str_replace('  ', ' ', $new);
            $new = str_replace('+} {+', ' ', $new);
            $new = preg_replace('!\{\+(.*)\+\}!U', '<ins>\\1</ins>', $new);
        }

        $out .= '<tr>';
        $out .= '<td class="line">'.($i+1).'</td>';
        $out .= '<td class="leftChange">'.$t1.'</td>';
        $out .= '<td class="leftText '.$class1.'">'.$old.'</td>';
        $out .= '<td class="rightChange">'.$t2.'</td>';
        $out .= '<td class="rightText '.$class2.'">'.$new.'</td>';
        $out .= '</tr>';

        $prev = $i;
    }

    $out .= '</table>';
    return $out;
}