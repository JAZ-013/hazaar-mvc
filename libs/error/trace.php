
<html>
<head>

    <title>Hazaar MVC - Backtrace</title>

    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            padding: 0;
            margin: 0;
            color: #333;
        }

        .container {
            max-width: 1170px;
            margin: auto;
        }

        .topbar {
            background-color: #554D7C;
            color: #fff;
            padding: 15px;
        }

        h1 {
            margin-bottom: 0;
        }

        h3 {
            margin-top: 0;
            font-weight: normal;
            font-style: italic;
        }

        table {
            font-size: 18px;
            width: 100%;
            border-collapse: collapse;
        }

            table th {
                color: #554D7C;
            }

            table td, table th {
                padding: 15px 15px 15px 0;
            }

            table.trace {
                background: #eee;
            }

                table.trace th {
                    border-bottom: 1px solid #554D7C;
                }

                table.trace th, table.trace td {
                    padding: 15px;
                }

                table.trace tbody tr:nth-child(2) {
                }

        th {
            text-align: left;
            vertical-align: top;
            width: 50px;
        }
    </style>
</head>
<body>

    <div class="topbar">

        <div class="container">

            <h1>BACKTRACE</h1>

            <h3>Please enjoy your debug backtrace...</h3>

        </div>

    </div>


    <div class="container">

        <table>
            <tr>
                <th>File</th>
                <th>Line</th>
                <th>Function</th>
                <th>Args</th>
            </tr>

            <?php

            foreach($trace as $t){

                echo "<tr><td>" . ake($t, 'file', 'unknown') . "</td><td>" . ake($t, 'line') . "</td><td>" . $t['function'] . "</td>";

                if($args = ake($t, 'args')){

                    $arglist = array();

                    foreach($args as $a){

                        if(is_object($a)){

                            $arglist[] = "Object(" . get_class($a) . ")";

                        }elseif(is_array($a)){

                            $arglist[] = var_export($a, true);

                        }else{

                            $arglist[] = gettype($a) . "($a)";

                        }

                    }

                    echo "<td>" . implode(", ", $arglist) . "</td>";

                }

                echo "</tr>";

            }

            ?>
        </table>
    </div>
</body>
</html>