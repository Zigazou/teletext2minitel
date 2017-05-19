<?php
function setColors($foreground, $background, $sep) {
    return chr(0x1b) . chr(0x40 + $foreground)
         . chr(0x1b) . chr(0x50 + $background)
         . ($sep ? chr(0x1b) . chr(0x5a)
                 : chr(0x1b) . chr(0x59));
}

function convertRow($row) {
    $destination = "";
    $gfx = FALSE;
    $sep = FALSE;
    $fg = 7;
    $bg = 0;
    $hold = FALSE;
    $held = 0x20;

    // Each line in Teletext starts with default attributes
    $destination .= chr(0x1b) . chr(0x47) . chr(0x1b) . chr(0x50) . chr(0x0f);

    // If an empty row, go to next row
    if(trim($row) === "") return $destination . chr(0x0d) . chr(0x0a);

    for($i = 0; $i < strlen($row); $i++) {
        $c = ord($row[$i]);
        $control = "";

        switch($c) {
            // Set text mode and color
            case 0x01: case 0x02: case 0x03: case 0x04: case 0x05: case 0x06:
            case 0x07:
                $gfx = FALSE;
                $fg = $c;
                $control = chr(0x0f) . setColors($fg, $bg, $sep && $gfx);
                break;

            case 0x08: case 0x09: case 0x0b: case 0x0c: case 0x0d:
                $control = chr(0x1b) . chr(0x40 + $c);
                break;

            // Ignore...
            case 0x0a: case 0x0e: case 0x0f: case 0x10: break;

            // Set graphics mode and color
            case 0x11: case 0x12: case 0x13: case 0x14: case 0x15: case 0x16:
            case 0x17:
                $gfx = TRUE;
                $fg = $c - 0x10;
                $control = chr(0x0e) . setColors($fg, $bg, $sep && $gfx);
                break;

            case 0x18:
                $control = chr(0x1b) . chr(0x40 + $c);
                break;

            // Set continuous graphics
            case 0x19:
                $sep = FALSE;
                $control = chr(0x1b) . chr(0x59);
                break;

            // Set separated graphics
            case 0x1a:
                $sep = TRUE;
                $control = chr(0x1b) . chr(0x5a);
                break;

            // Set black background
            case 0x1c:
                $invert = FALSE;
                $bg = 0;
                $control = setColors($fg, $bg, $sep && $gfx);
                break;

            // Swap foreground and background colors
            case 0x1d:
                list($bg, $fg) = array($fg, $bg);
                $control = setColors($fg, $bg, $sep && $gfx);
                break;

            // Hold graphics
            case 0x1e:
                $hold = TRUE;
                break;

            // Release graphics            
            case 0x1f:
                $hold = FALSE;
                break;
        }

        if($c < 0x20) {
            if($hold or $held != 0x20) {
                if($c != 0x1d) {
                    $destination .= chr($held) . $control;
                } else {
                    $destination .= $control . chr($held);
                }
            } else {
                $destination .= $control . chr(0x20);
            }
            if(!$hold) $held = 0x20;
        } else {
            if($gfx) {
                if($c >= 0x40 and $c <=0x5f) {
                    // In graphics mode capital letters are still characters
                    $destination .= chr(0x0f) . chr($c) . chr(0x0e);
                    if($hold and $c & 0x20) $held = $c;
                } elseif($c >= 0x60) {
                    // Convert Teletext mosaic chars to Minitel mosaic chars
                    $destination .= chr($c - 0x20);
                    if($hold and $c & 0x20) $held = $c - 0x20;
                } else {
                    // Everything else is copied as is
                    $destination .= chr($c);
                    if($hold and $c & 0x20) $held = $c;
                }
            } else {
                // Everything else is copied as is
                $destination .= chr($c);
            }
        }
    }

    return $destination;
}

function convertPage($page) {
    $rows = explode(chr(0x0a), $page);

    // Trim the first line
    array_shift($rows);

    $destination = "";
    foreach($rows as $row) {
        $destination .= convertRow($row);
    }

    return $destination;
}

function convert($input, $output) {
    $source = file_get_contents($input);
    $destination = chr(0x0c) . convertPage($source);
    file_put_contents($output, $destination);
}

if (PHP_SAPI != "cli") exit;
convert($argv[1], $argv[2]);

