<html xmlns=\"http://www.w3.org/1999/xhtml\">
<head>
    <meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\"/>
    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1\"/>
    <title>Narrative Confirm Email</title>
    <style type=\"text/css\">
        img {
            max-width: 600px;
            outline: none;
            text-decoration: none;
            -ms-interpolation-mode: bicubic;
        }

        a {
            border: 0;
            outline: none;
        }

        a img {
            border: none;
        }

        td, h1, h2, h3 {
            font-family: Helvetica, Arial, sans-serif;
            font-weight: 400;
        }

        td {
            font-size: 13px;
            line-height: 150%;
            text-align: left;
        }

        body {
            -webkit-font-smoothing: antialiased;
            -webkit-text-size-adjust: none;
            width: 100%;
            height: 100%;
            color: #37302d;
            background: #ffffff;
        }

        table {
            border-collapse: collapse !important;
        }

        h1, h2, h3 {
            padding: 0;
            margin: 0;
            color: #444444;
            font-weight: 400;
            line-height: 110%;
        }

        h1 {
            font-size: 35px;
        }

        h2 {
            font-size: 30px;
        }

        h3 {
            font-size: 24px;
        }

        h4 {
            font-size: 18px;
            font-weight: normal;
        }

        .important-font {
            color: #21BEB4;
            font-weight: bold;
        }

        .hide {
            display: none !important;
        }

        .force-full-width {
            width: 100% !important;
        }
    </style>
    <style type=\"text/css\" media=\"screen\">
        @media screen {
            @import url(http://fonts.googleapis.com/css?family=Open+Sans:400) /* Thanks Outlook 2013! http://goo.gl/XLxpyl */;
            td, h1, h2, h3 {
                font-family: 'Open Sans', 'Helvetica Neue', Arial, sans-serif !important;
            }
        }
    </style>
    <style type="text/css\" media="only screen and (max-width: 600px)">
        /* Mobile styles */
        @media only screen and (max-width: 600px) {
            table[class=\"w320\"] {
                width: 320px !important;
            }

            table[class=\"w300\"] {
                width: 300px !important;
            }

            table[class=\"w290\"] {
                width: 290px !important;
            }

            td[class=\"w320\"] {
                width: 320px !important;
            }

            td[class~=\"mobile-padding\"] {
                padding-left: 14px !important;
                padding-right: 14px !important;
            }

            td[class*=\"mobile-padding-left\"] {
                padding-left: 14px !important;
            }

            td[class*=\"mobile-padding-right\"] {;
                padding-right: 14px !important;
            }

            td[class*=\"mobile-block\"] {
                display: block !important;
                width: 100% !important;
                text-align: left !important;
                padding-left: 0 !important;
                padding-right: 0 !important;
                padding-bottom: 15px !important;
            }

            td[class*=\"mobile-no-padding-bottom\"] {
                padding-bottom: 0 !important;
            }

            td[class~=\"mobile-center\"] {
                text-align: center !important;
            }

            table[class*=\"mobile-center-block\"] {
                float: none !important;
                margin: 0 auto !important;
            }

            *[class*=\"mobile-hide\"] {
                display: none !important;
                width: 0 !important;
                height: 0 !important;
                line-height: 0 !important;
                font-size: 0 !important;
            }

            td[class*=\"mobile-border\"] {
                border: 0 !important;
            }
        }
    </style>
</head>
<body class="body" style="padding:0; margin:0; display:block; background:#ffffff; -webkit-text-size-adjust:none"
      bgcolor="#ffffff">
<table align="center" cellpadding="0" cellspacing="0" width="100%" height="100%">
    <tr>
        <td align="center" valign="top" bgcolor="#ffffff" width="100%">
            <table cellspacing="0" cellpadding="0" width="100%">
                <tr>
                    <td style="background:#014401" width="100%">
                        <center>
                            <table cellspacing="0" cellpadding="0" width="750" class="w320">
                                <tr>
                                    <td valign="top" class="mobile-block mobile-no-padding-bottom mobile-center"
                                        width="800"
                                        style="color:#fff">
                                        <p style="font-weight: bold">
                                            Ministry of Education - Zambia<br/>
                                            Girls Education and Women ‘s Empowerment <br/>
                                            Keeping Girls in School Management Information System
                                           {{-- {{$program_name}}--}}
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </center>
                    </td>
                </tr>
                <tr>
                    <td style="border-bottom:1px solid #e7e7e7;">
                        <center>
                            <table cellpadding="0" cellspacing="0" width="750" class="w320">
                                <tr>
                                    <td align="left" class="mobile-padding" style="padding:20px">
                                        <span style="font-size: x-large">Greetings,</span>
                                        <br/><br/>
                                        <span style="font-size: x-large">{!!html_entity_decode($email_body)!!}</span>
                                        <br/><br/>
                                        @yield('content')
                                        <br>
                                    </td>
                                </tr>
                            </table>
                        </center>
                    </td>
                </tr>
                <tr>
                    <td valign="top" style="background-color:#f8f8f8;border-bottom:1px solid #e7e7e7;">
                        <center>
                            <table border="0" cellpadding="0" cellspacing="0" width="750" class="w320"
                                   style="height:100%;">
                                <tr>
                                    <td valign="top" class="mobile-padding" style="padding:10px;">
                                        <table cellspacing="0" cellpadding="0" width="100%">
                                            <tr>
                                                <td>
                                                    <b>
                                                        MOE - Headquarters;
                                                        89 Corner of Chimanga and Mogadishu Road,
                                                        P.O. Box 50093 Lusaka, Zambia.<br/>
                                                        E-mail: info@MOGE.gov.zm<br/>
                                                        Tel: +260 211 250855<br/>
                                                        Fax: +260 1 250760
                                                    </b>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </center>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
