<?php return array(
    "modules" => array(
        "BGanon" => array(
            "root_dir" => "BGanon",
            "bootstrap" => array("file" =>"BGanon.php", "callback" =>"BGanon::bootstrap"),
            "version" => "0.5.0",
        ),
        "BPHPTAL" => array(
            "root_dir" => "BPHPTAL",
            "bootstrap" => array("file" =>"BPHPTAL.php", "callback" =>"BPHPTAL::bootstrap"),
            "version" => "1.2.2-0",
        ),
        "BTwig" => array(
            "root_dir" => "BTwig",
            "bootstrap" => array("file" =>"BTwig.php", "callback" =>"BTwig::bootstrap"),
            "version" => "1.12.4",
        ),
        "BYAML" => array(
            "root_dir" => "BYAML",
            "bootstrap" => array("file" =>"BYAML.php", "callback" =>"BYAML::bootstrap"),
            "version" => "0.5",
        ),
        "BUI" => array(
            "root_dir" => "BUI",
            "bootstrap" => array("file" =>"BUI.php", "callback" =>"BUI::bootstrap"),
            "version" => "0.0.1",
        ),
        "BFireLogger" => array(
            "root_dir" => "BFireLogger",
            "bootstrap" => array("file" =>"BFireLogger.php", "callback" =>"BFireLogger::bootstrap"),
            "version" => "1.0.0",
        ),
        "BMarkdownExtra" => array(
            "root_dir" => "MarkdownExtra",
            "bootstrap" => array("file" =>"markdown.php", "callback" =>"BMarkdown_Extra_Loader::bootstrap"),
            "version" => "1.0.0",
        ),
        "BHighcharts" => array(
            "root_dir" => "BHighcharts",
            "areas" =>array(
                "FCom_Admin" =>array(
                    "bootstrap" => array("file" =>"Highchart.php", "callback" =>"BHighcharts_Loader::bootstrap"),
                ),
            ),
            "version" => "1.0.0",
        ),
    ),
);