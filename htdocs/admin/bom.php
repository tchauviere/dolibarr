<?php
/* Copyright (C) 2019 Laurent Destailleur          <eldy@users.sourceforge.net>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *	\file       htdocs/admin/bom.php
 *	\ingroup    bom
 *	\brief      Setup page of module BOM
 */

require '../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
require_once DOL_DOCUMENT_ROOT.'/bom/class/bom.class.php';
require_once DOL_DOCUMENT_ROOT.'/bom/lib/bom.lib.php';

// Load translation files required by the page
$langs->loadLangs(array('admin', 'errors', 'mrp', 'other'));

if (! $user->admin) accessforbidden();

$action = GETPOST('action', 'alpha');
$value = GETPOST('value', 'alpha');
$label = GETPOST('label', 'alpha');
$scandir = GETPOST('scan_dir', 'alpha');
$type = 'bom';


/*
 * Actions
 */

include DOL_DOCUMENT_ROOT.'/core/actions_setmoduleoptions.inc.php';

if ($action == 'updateMask')
{
	$maskconstbom=GETPOST('maskconstBom', 'alpha');
	$maskbom=GETPOST('maskBom', 'alpha');

	if ($maskconstbom) $res = dolibarr_set_const($db, $maskconstbom, $maskbom, 'chaine', 0, '', $conf->entity);

	if (! $res > 0) $error++;

 	if (! $error)
    {
        setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
    }
    else
    {
        setEventMessages($langs->trans("Error"), null, 'errors');
    }
}

elseif ($action == 'specimen')
{
	$modele=GETPOST('module', 'alpha');

	$bom = new BOM($db);
	$bom->initAsSpecimen();

	// Search template files
	$file=''; $classname=''; $filefound=0;
	$dirmodels=array_merge(array('/'), (array) $conf->modules_parts['models']);
	foreach($dirmodels as $reldir)
	{
	    $file=dol_buildpath($reldir."core/modules/bom/doc/pdf_".$modele.".modules.php", 0);
		if (file_exists($file))
		{
			$filefound=1;
			$classname = "pdf_".$modele;
			break;
		}
	}

	if ($filefound)
	{
		require_once $file;

		$module = new $classname($db);

		if ($module->write_file($bom, $langs) > 0)
		{
			header("Location: ".DOL_URL_ROOT."/document.php?modulepart=bom&file=SPECIMEN.pdf");
			return;
		}
		else
		{
			setEventMessages($module->error, null, 'errors');
			dol_syslog($module->error, LOG_ERR);
		}
	}
	else
	{
		setEventMessages($langs->trans("ErrorModuleNotFound"), null, 'errors');
		dol_syslog($langs->trans("ErrorModuleNotFound"), LOG_ERR);
	}
}

// Activate a model
elseif ($action == 'set')
{
	$ret = addDocumentModel($value, $type, $label, $scandir);
}

elseif ($action == 'del')
{
	$ret = delDocumentModel($value, $type);
	if ($ret > 0)
	{
        if ($conf->global->BOM_ADDON_PDF == "$value") dolibarr_del_const($db, 'BOM_ADDON_PDF', $conf->entity);
	}
}

// Set default model
elseif ($action == 'setdoc')
{
	if (dolibarr_set_const($db, "BOM_ADDON_PDF", $value, 'chaine', 0, '', $conf->entity))
	{
		// The constant that was read before the new set
		// We therefore requires a variable to have a coherent view
		$conf->global->BOM_ADDON_PDF = $value;
	}

	// On active le modele
	$ret = delDocumentModel($value, $type);
	if ($ret > 0)
	{
		$ret = addDocumentModel($value, $type, $label, $scandir);
	}
}

elseif ($action == 'setmod')
{
	// TODO Check if numbering module chosen can be activated
	// by calling method canBeActivated

	dolibarr_set_const($db, "BOM_ADDON", $value, 'chaine', 0, '', $conf->entity);
}

elseif ($action == 'set_BOM_DRAFT_WATERMARK')
{
	$draft = GETPOST("BOM_DRAFT_WATERMARK");
	$res = dolibarr_set_const($db, "BOM_DRAFT_WATERMARK", trim($draft), 'chaine', 0, '', $conf->entity);

	if (! $res > 0) $error++;

 	if (! $error)
    {
        setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
    }
    else
    {
        setEventMessages($langs->trans("Error"), null, 'errors');
    }
}

elseif ($action == 'set_BOM_FREE_TEXT')
{
	$freetext = GETPOST("BOM_FREE_TEXT", 'none');	// No alpha here, we want exact string

	$res = dolibarr_set_const($db, "BOM_FREE_TEXT", $freetext, 'chaine', 0, '', $conf->entity);

	if (! $res > 0) $error++;

 	if (! $error)
    {
        setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
    }
    else
    {
        setEventMessages($langs->trans("Error"), null, 'errors');
    }
} elseif ($action=="setshippableiconinlist") {
    // Activate Set Shippable Icon In List
    $setshippableiconinlist = GETPOST('value', 'int');
    $res = dolibarr_set_const($db, "SHIPPABLE_BOM_ICON_IN_LIST", $setshippableiconinlist, 'yesno', 0, '', $conf->entity);
    if (! $res > 0) $error++;
    if (! $error) {
        setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
    } else {
        setEventMessages($langs->trans("Error"), null, 'errors');
    }
}


/*
 * View
 */

$form=new Form($db);

$dirmodels=array_merge(array('/'), (array) $conf->modules_parts['models']);

llxHeader("", $langs->trans("BOMsSetup"));

$linkback='<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($langs->trans("BOMsSetup"), $linkback, 'title_setup');

$head = bomAdminPrepareHead();

dol_fiche_head($head, 'settings', $langs->trans("BOMs"), -1, 'bom');

/*
 * BOMs Numbering model
 */

print load_fiche_titre($langs->trans("BOMsNumberingModules"), '', '');

print '<table class="noborder" width="100%">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("Name").'</td>';
print '<td>'.$langs->trans("Description").'</td>';
print '<td class="nowrap">'.$langs->trans("Example").'</td>';
print '<td class="center" width="60">'.$langs->trans("Status").'</td>';
print '<td class="center" width="16">'.$langs->trans("ShortInfo").'</td>';
print '</tr>'."\n";

clearstatcache();

foreach ($dirmodels as $reldir)
{
	$dir = dol_buildpath($reldir."core/modules/bom/");

	if (is_dir($dir))
	{
		$handle = opendir($dir);
		if (is_resource($handle))
		{
			while (($file = readdir($handle))!==false)
			{
			    if (substr($file, 0, 8) == 'mod_bom_' && substr($file, dol_strlen($file)-3, 3) == 'php')
				{
					$file = substr($file, 0, dol_strlen($file)-4);

					require_once $dir.$file.'.php';

					$module = new $file($db);

					// Show modules according to features level
					if ($module->version == 'development'  && $conf->global->MAIN_FEATURES_LEVEL < 2) continue;
					if ($module->version == 'experimental' && $conf->global->MAIN_FEATURES_LEVEL < 1) continue;

					if ($module->isEnabled())
					{
						print '<tr class="oddeven"><td>'.$module->name."</td><td>\n";
						print $module->info();
						print '</td>';

                        // Show example of numbering model
                        print '<td class="nowrap">';
                        $tmp=$module->getExample();
                        if (preg_match('/^Error/', $tmp)) print '<div class="error">'.$langs->trans($tmp).'</div>';
                        elseif ($tmp=='NotConfigured') print $langs->trans($tmp);
                        else print $tmp;
                        print '</td>'."\n";

						print '<td class="center">';
						if ($conf->global->BOM_ADDON == $file)
						{
							print img_picto($langs->trans("Activated"), 'switch_on');
						}
						else
						{
							print '<a class="reposition" href="'.$_SERVER["PHP_SELF"].'?action=setmod&amp;value='.$file.'">';
							print img_picto($langs->trans("Disabled"), 'switch_off');
							print '</a>';
						}
						print '</td>';

						$bom=new BOM($db);
						$bom->initAsSpecimen();

						// Info
						$htmltooltip='';
						$htmltooltip.=''.$langs->trans("Version").': <b>'.$module->getVersion().'</b><br>';
						$bom->type=0;
						$nextval=$module->getNextValue($mysoc, $bom);
                        if ("$nextval" != $langs->trans("NotAvailable")) {  // Keep " on nextval
                            $htmltooltip.=''.$langs->trans("NextValue").': ';
                            if ($nextval) {
                                if (preg_match('/^Error/', $nextval) || $nextval=='NotConfigured')
                                    $nextval = $langs->trans($nextval);
                                $htmltooltip.=$nextval.'<br>';
                            } else {
                                $htmltooltip.=$langs->trans($module->error).'<br>';
                            }
                        }

						print '<td class="center">';
						print $form->textwithpicto('', $htmltooltip, 1, 0);
						print '</td>';

						print "</tr>\n";
					}
				}
			}
			closedir($handle);
		}
	}
}
print "</table><br>\n";


if ($conf->global->MAIN_FEATURES_LEVEL >= 2)
{
    /*
     * Document templates generators
     */

    print load_fiche_titre($langs->trans("BOMsModelModule"), '', '');

    // Load array def with activated templates
    $def = array();
    $sql = "SELECT nom";
    $sql.= " FROM ".MAIN_DB_PREFIX."document_model";
    $sql.= " WHERE type = '".$type."'";
    $sql.= " AND entity = ".$conf->entity;
    $resql=$db->query($sql);
    if ($resql)
    {
    	$i = 0;
    	$num_rows=$db->num_rows($resql);
    	while ($i < $num_rows)
    	{
    		$array = $db->fetch_array($resql);
    		array_push($def, $array[0]);
    		$i++;
    	}
    }
    else
    {
    	dol_print_error($db);
    }


    print "<table class=\"noborder\" width=\"100%\">\n";
    print "<tr class=\"liste_titre\">\n";
    print '<td>'.$langs->trans("Name").'</td>';
    print '<td>'.$langs->trans("Description").'</td>';
    print '<td class="center" width="60">'.$langs->trans("Status")."</td>\n";
    print '<td class="center" width="60">'.$langs->trans("Default")."</td>\n";
    print '<td class="center" width="38">'.$langs->trans("ShortInfo").'</td>';
    print '<td class="center" width="38">'.$langs->trans("Preview").'</td>';
    print "</tr>\n";

    clearstatcache();

    foreach ($dirmodels as $reldir)
    {
        foreach (array('','/doc') as $valdir)
        {
        	$dir = dol_buildpath($reldir."core/modules/bom".$valdir);

            if (is_dir($dir))
            {
                $handle=opendir($dir);
                if (is_resource($handle))
                {
                    while (($file = readdir($handle))!==false)
                    {
                        $filelist[]=$file;
                    }
                    closedir($handle);
                    arsort($filelist);

                    foreach($filelist as $file)
                    {
                        if (preg_match('/\.modules\.php$/i', $file) && preg_match('/^(pdf_|doc_)/', $file))
                        {
                        	if (file_exists($dir.'/'.$file))
                        	{
                        		$name = substr($file, 4, dol_strlen($file) -16);
    	                        $classname = substr($file, 0, dol_strlen($file) -12);

    	                        require_once $dir.'/'.$file;
    	                        $module = new $classname($db);

    	                        $modulequalified=1;
    	                        if ($module->version == 'development'  && $conf->global->MAIN_FEATURES_LEVEL < 2) $modulequalified=0;
    	                        if ($module->version == 'experimental' && $conf->global->MAIN_FEATURES_LEVEL < 1) $modulequalified=0;

    	                        if ($modulequalified)
    	                        {
    	                            $var = !$var;
    	                            print '<tr class="oddeven"><td width="100">';
    	                            print (empty($module->name)?$name:$module->name);
    	                            print "</td><td>\n";
    	                            if (method_exists($module, 'info')) print $module->info($langs);
    	                            else print $module->description;
    	                            print '</td>';

    	                            // Active
    	                            if (in_array($name, $def))
    	                            {
    	                            	print '<td class="center">'."\n";
    	                            	print '<a href="'.$_SERVER["PHP_SELF"].'?action=del&value='.$name.'">';
    	                            	print img_picto($langs->trans("Enabled"), 'switch_on');
    	                            	print '</a>';
    	                            	print '</td>';
    	                            }
    	                            else
    	                            {
    	                                print '<td class="center">'."\n";
    	                                print '<a href="'.$_SERVER["PHP_SELF"].'?action=set&value='.$name.'&amp;scan_dir='.$module->scandir.'&amp;label='.urlencode($module->name).'">'.img_picto($langs->trans("Disabled"), 'switch_off').'</a>';
    	                                print "</td>";
    	                            }

    	                            // Default
    	                            print '<td class="center">';
    	                            if ($conf->global->BOM_ADDON_PDF == $name)
    	                            {
    	                                print img_picto($langs->trans("Default"), 'on');
    	                            }
    	                            else
    	                            {
    	                                print '<a href="'.$_SERVER["PHP_SELF"].'?action=setdoc&value='.$name.'&amp;scan_dir='.$module->scandir.'&amp;label='.urlencode($module->name).'" alt="'.$langs->trans("Default").'">'.img_picto($langs->trans("Disabled"), 'off').'</a>';
    	                            }
    	                            print '</td>';

    	                            // Info
    		    					$htmltooltip =    ''.$langs->trans("Name").': '.$module->name;
    					    		$htmltooltip.='<br>'.$langs->trans("Type").': '.($module->type?$module->type:$langs->trans("Unknown"));
    			                    if ($module->type == 'pdf')
    			                    {
    			                        $htmltooltip.='<br>'.$langs->trans("Width").'/'.$langs->trans("Height").': '.$module->page_largeur.'/'.$module->page_hauteur;
    			                    }
    					    		$htmltooltip.='<br><br><u>'.$langs->trans("FeaturesSupported").':</u>';
    					    		$htmltooltip.='<br>'.$langs->trans("Logo").': '.yn($module->option_logo, 1, 1);
    					    		$htmltooltip.='<br>'.$langs->trans("PaymentMode").': '.yn($module->option_modereg, 1, 1);
    					    		$htmltooltip.='<br>'.$langs->trans("PaymentConditions").': '.yn($module->option_condreg, 1, 1);
    					    		$htmltooltip.='<br>'.$langs->trans("MultiLanguage").': '.yn($module->option_multilang, 1, 1);
    					    		//$htmltooltip.='<br>'.$langs->trans("Discounts").': '.yn($module->option_escompte,1,1);
    					    		//$htmltooltip.='<br>'.$langs->trans("CreditNote").': '.yn($module->option_credit_note,1,1);
    					    		$htmltooltip.='<br>'.$langs->trans("WatermarkOnDraftBOMs").': '.yn($module->option_draft_watermark, 1, 1);


    	                            print '<td class="center">';
    	                            print $form->textwithpicto('', $htmltooltip, 1, 0);
    	                            print '</td>';

    	                            // Preview
    	                            print '<td class="center">';
    	                            if ($module->type == 'pdf')
    	                            {
    	                                print '<a href="'.$_SERVER["PHP_SELF"].'?action=specimen&module='.$name.'">'.img_object($langs->trans("Preview"), 'bill').'</a>';
    	                            }
    	                            else
    	                            {
    	                                print img_object($langs->trans("PreviewNotAvailable"), 'generic');
    	                            }
    	                            print '</td>';

    	                            print "</tr>\n";
    	                        }
                        	}
                        }
                    }
                }
            }
        }
    }

    print '</table>';
    print "<br>";

    /*
     * Other options
     */

    print load_fiche_titre($langs->trans("OtherOptions"), '', '');
    print '<table class="noborder" width="100%">';
    print '<tr class="liste_titre">';
    print '<td>'.$langs->trans("Parameter").'</td>';
    print '<td class="center" width="60">'.$langs->trans("Value").'</td>';
    print "<td>&nbsp;</td>\n";
    print "</tr>\n";

    $substitutionarray=pdf_getSubstitutionArray($langs, null, null, 2);
    $substitutionarray['__(AnyTranslationKey)__']=$langs->trans("Translation");
    $htmltext = '<i>'.$langs->trans("AvailableVariables").':<br>';
    foreach($substitutionarray as $key => $val)	$htmltext.=$key.'<br>';
    $htmltext.='</i>';

    print '<form action="'.$_SERVER["PHP_SELF"].'" method="post">';
    print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
    print '<input type="hidden" name="action" value="set_BOM_FREE_TEXT">';
    print '<tr class="oddeven"><td colspan="2">';
    print $form->textwithpicto($langs->trans("FreeLegalTextOnBOMs"), $langs->trans("AddCRIfTooLong").'<br><br>'.$htmltext, 1, 'help', '', 0, 2, 'freetexttooltip').'<br>';
    $variablename='BOM_FREE_TEXT';
    if (empty($conf->global->PDF_ALLOW_HTML_FOR_FREE_TEXT))
    {
        print '<textarea name="'.$variablename.'" class="flat" cols="120">'.$conf->global->$variablename.'</textarea>';
    }
    else
    {
        include_once DOL_DOCUMENT_ROOT.'/core/class/doleditor.class.php';
        $doleditor=new DolEditor($variablename, $conf->global->$variablename, '', 80, 'dolibarr_notes');
        print $doleditor->Create();
    }
    print '</td><td class="right">';
    print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
    print "</td></tr>\n";
    print '</form>';

    //Use draft Watermark

    print "<form method=\"post\" action=\"".$_SERVER["PHP_SELF"]."\">";
    print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
    print "<input type=\"hidden\" name=\"action\" value=\"set_BOM_DRAFT_WATERMARK\">";
    print '<tr class="oddeven"><td>';
    print $form->textwithpicto($langs->trans("WatermarkOnDraftBOMs"), $htmltext, 1, 'help', '', 0, 2, 'watermarktooltip').'<br>';
    print '</td><td>';
    print '<input class="flat minwidth200" type="text" name="BOM_DRAFT_WATERMARK" value="'.$conf->global->BOM_DRAFT_WATERMARK.'">';
    print '</td><td class="right">';
    print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
    print "</td></tr>\n";
    print '</form>';

    print '</table>';
    print '<br>';
}

/*
 * Notifications
 */
/*
print load_fiche_titre($langs->trans("Notifications"), '', '');
print '<table class="noborder" width="100%">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("Parameter").'</td>';
print '<td class="center" width="60"></td>';
print '<td width="80">&nbsp;</td>';
print "</tr>\n";

print '<tr class="oddeven"><td colspan="2">';
print $langs->trans("YouMayFindNotificationsFeaturesIntoModuleNotification").'<br>';
print '</td><td class="right">';
print "</td></tr>\n";

print '</table>';
*/

// End of page
llxFooter();
$db->close();
