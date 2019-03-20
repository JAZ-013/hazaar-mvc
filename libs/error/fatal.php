<html>
<head>

    <title>Hazaar MVC - Fatal Error</title>

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

            <h1>FATAL ERROR</h1>

            <h3>An error occurred without an application context...</h3>

        </div>

    </div>


    <div class="container">

        <h2>
            <?=$error[1]?>
        </h2>

        <table>
            <tr>
                <th>File:</th>
                <td>
                    <?=$error[2];?>
                </td>
            </tr>
            <tr>
                <th>Line:</th>
                <td>
                    <?=$error[3];?>
                </td>
            </tr>
            <tr>
                <th>Trace:</th>
                <td>
                    <table class="trace">

                        <thead>

                            <tr>
                                <th>Step</th>
                                <th>File</th>
                                <th>Line</th>
                                <th>Function</th>
                            </tr>

                        </thead>

                        <tbody>

                            <?php foreach($error[5] as $id => $trace) { ?>

                            <tr>
                                <td>
                                    <?=$id;?>
                                </td>
                                <td>
                                    <?=ake($trace, 'file');?>
                                </td>
                                <td>
                                    <?=ake($trace, 'line');?>
                                </td>
                                <td>
                                    <?php
                                      echo (isset($trace['class']) ? $trace['class'] . '::' : NULL) . ake($trace, 'function') . '(';
                                      if(isset($trace['args']) && is_array($trace['args'])){
                                          array_walk($trace['args'], function(&$item){
                                              if(is_array($item))
                                                  $item = 'Array(' . count($item) . ')';
                                              elseif( is_string($item))
                                                  $item = "'$item'";
                                          });
                                          echo implode(', ', $trace['args']);
                                      }
                                      echo ')';?>
                                </td>
                            </tr>

                            <?php } ?>

                        </tbody>
                    </table>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>