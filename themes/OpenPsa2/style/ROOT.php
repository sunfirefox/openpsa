<?php
$i18n = midcom::get('i18n');
$head = midcom::get('head');
$context = midcom_core_context::get();

if (!defined('MIDCOM_STATIC_URL'))
{
    define('MIDCOM_STATIC_URL', '/midcom-static');
}

$pref_found = false;
$width = midgard_admin_asgard_plugin::get_preference('openpsa2_offset');
if ($width !== false)
{
    $navigation_width = $width - 2;
    $content_offset = $width;
    $pref_found = true;
}

$topic = $context->get_key(MIDCOM_CONTEXT_CONTENTTOPIC);

echo "<?xml version=\"1.0\"?>\n";
?>
<!DOCTYPE html
    PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
    "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="<?php echo $i18n->get_current_language(); ?>">
    <head>
        <meta http-equiv="Content-Type" content="text/xhtml; charset=utf-8" />
        <title><?php echo $topic->extra . ': ' . $context->get_key(MIDCOM_CONTEXT_PAGETITLE); ?> - <(title)> OpenPSA</title>
        <link type="image/x-icon" href="<?php echo MIDCOM_STATIC_URL; ?>/org.openpsa.core/openpsa-16x16.png" rel="shortcut icon"/>
        <?php
          $head->add_stylesheet(MIDCOM_STATIC_URL . '/OpenPsa2/style.css', 'screen,projection');
          $head->add_stylesheet(MIDCOM_STATIC_URL . '/OpenPsa2/content.css', 'all');
          $head->add_stylesheet(MIDCOM_STATIC_URL . '/OpenPsa2/print.css', 'print');
          $head->add_stylesheet(MIDCOM_STATIC_URL . '/OpenPsa2/ui-elements.css', 'all');

        $head->enable_jquery();
        $head->add_jsfile(MIDCOM_JQUERY_UI_URL . '/ui/jquery.ui.core.min.js');
        $head->add_jsfile(MIDCOM_JQUERY_UI_URL . '/ui/jquery.ui.widget.min.js');
        $head->add_jsfile(MIDCOM_JQUERY_UI_URL . '/ui/jquery.ui.mouse.min.js');
        $head->add_jsfile(MIDCOM_JQUERY_UI_URL . '/ui/jquery.ui.draggable.min.js');
        org_openpsa_widgets_ui::add_head_elements();
        org_openpsa_widgets_ui::initialize_search();

        $head->add_jscript("var MIDGARD_ROOT = '" . midcom_connection::get_url('self') . "';");
        $head->add_jscript("var TOOLBAR_MORE_LABEL = '" . midcom::get('i18n')->get_l10n('org.openpsa.widgets')->get('more') . "';");
        $head->print_head_elements();

        if ($pref_found)
        {?>
            <style type="text/css">
            #container #leftframe
            {
                width: &(navigation_width);px;
            }

            #container #content
            {
                margin-left: &(content_offset);px;
            }
            </style>
        <?php } ?>

    </head>
    <body<?php $head->print_jsonload(); ?>>
        <(toolbar)>
        <div id="container">
          <div id="leftframe">
            <div id="branding">
                <(logo)>
            </div>
            <div id="nav">
                <(navigation)>
            </div>
          </div>
          <div id="content">
              <div id="content-menu">
                  <(breadcrumb)>
                  <div class="context">
                      <(userinfo)>
                      <(search)>
                  </div>
              </div>
              <div id="org_openpsa_toolbar" class="org_openpsa_toolbar">
                  <(toolbar-bottom)>
              </div>
              <div id="org_openpsa_messagearea">
              </div>
              <div id="content-text">
                  <?php
                  //Display any UI messages added to stack on PHP level
                  midcom::get('uimessages')->show();
                  ?>
                  <(content)>
              </div>
          </div>
       </div>

    <script type="text/javascript">
    //This has to be timed with the jqgrid resizers
    org_openpsa_layout.resize_content('#content-text');

    jQuery(document).ready(function()
    {
        org_openpsa_layout.add_splitter();
        org_openpsa_layout.clip_toolbar();
        org_openpsa_layout.bind_admin_toolbar_loader();

        org_openpsa_jsqueue.execute()
    });
    </script>
    </body>
</html>