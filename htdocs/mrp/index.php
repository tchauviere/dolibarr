<?php
/* Copyright (C) 2001-2002	Rodolphe Quiedeville	<rodolphe@quiedeville.org>
 * Copyright (C) 2003		Jean-Louis Bergamo	    <jlb@j1b.org>
 * Copyright (C) 2004-2017	Laurent Destailleur	    <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2012	Regis Houssin		    <regis.houssin@inodbox.com>
 * Copyright (C) 2019       Nicolas ZABOURI         <info@inovea-conseil.com>
 * Copyright (C) 2019       Frédéric France         <frederic.france@netlogic.fr>
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
 *       \file       htdocs/mrp/index.php
 *       \ingroup    bom, mrp
 *       \brief      Home page for BOM and MRP modules
 */

require '../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/bom/class/bom.class.php';

$hookmanager = new HookManager($db);

// Initialize technical object to manage hooks. Note that conf->hooks_modules contains array
$hookmanager->initHooks(array('mrpindex'));

// Load translation files required by the page
$langs->loadLangs(array("companies","mrp"));

// Security check
$result=restrictedArea($user, 'bom|mrp');


/*
 * View
 */

$staticbom = new BOM($db);

llxHeader('', $langs->trans("MRP"), '');

print load_fiche_titre($langs->trans("MRPArea"), '', 'cubes');


print '<div class="fichecenter"><div class="fichethirdleft">';


/*
 * Statistics
 */

if ($conf->use_javascript_ajax)
{
    $sql= "SELECT COUNT(t.rowid) as nb, status";
    $sql.=" FROM ".MAIN_DB_PREFIX."mrp_mo as t";
	$sql.=" GROUP BY t.status";
    $resql = $db->query($sql);

    if ($resql)
    {
    	$num = $db->num_rows($resql);
    	$i = 0;

    	$totalnb=0;
    	$dataseries=array();
    	// -1=Canceled, 0=Draft, 1=Validated, (2=Accepted/On process not managed for customer orders), 3=Closed (Sent/Received, billed or not)
    	while ($i < $num)
    	{
    		$obj = $db->fetch_object($resql);
    		if ($obj)
    		{
    			//if ($row[1]!=-1 && ($row[1]!=3 || $row[2]!=1))
    			{
    				$dataseries[$obj->status]=$obj->nb;
    				$totalnb+=$obj->nb;
    			}
    		}
    		$i++;
    	}
    	$db->free($resql);

    	print '<div class="div-table-responsive-no-min">';
    	print '<table class="noborder nohover" width="100%">';
    	print '<tr class="liste_titre"><th colspan="2">'.$langs->trans("Statistics").'</th></tr>'."\n";
        if ($conf->use_javascript_ajax)
    	{
    		print '<tr><td class="center" colspan="2">';

    		include_once DOL_DOCUMENT_ROOT.'/core/class/dolgraph.class.php';
    		$dolgraph = new DolGraph();
    		$dolgraph->SetData($dataseries);
    		$dolgraph->setShowLegend(1);
    		$dolgraph->setShowPercent(1);
    		$dolgraph->SetType(array('pie'));
    		$dolgraph->setWidth('100%');
    		$dolgraph->SetHeight(180);
    		$dolgraph->draw('idgraphstatus');
    		print $dolgraph->show($totalnb?0:1);

    		print '</td></tr>';
    	}
    	print "</table>";
    	print "</div>";

    	print "<br>";
    }
    else
    {
    	dol_print_error($db);
    }
}

print '<br>';


print '</div><div class="fichetwothirdright"><div class="ficheaddleft">';

/*
 * Last modified BOM
 */
$max=5;

$sql = "SELECT a.rowid, a.status, a.ref, a.tms as datem, a.status";
$sql.= " FROM ".MAIN_DB_PREFIX."bom_bom as a";
$sql.= " WHERE a.entity IN (".getEntity('bom').")";
$sql.= $db->order("a.tms", "DESC");
$sql.= $db->plimit($max, 0);

$resql=$db->query($sql);
if ($resql)
{
	print '<div class="div-table-responsive-no-min">';
	print '<table class="noborder" width="100%">';
	print '<tr class="liste_titre">';
	print '<th colspan="4">'.$langs->trans("LatestBOMModified", $max).'</th></tr>';

	$num = $db->num_rows($resql);
	if ($num)
	{
		$i = 0;
		while ($i < $num)
		{
			$obj = $db->fetch_object($resql);

			$staticbom->id=$obj->rowid;
			$staticbom->ref=$obj->ref;
			$staticbom->date_modification=$obj->datem;
			$staticbom->status=$obj->status;

			print '<tr class="oddeven">';
			print '<td>'.$staticbom->getNomUrl(1, 32).'</td>';
			print '<td>'.dol_print_date($db->jdate($obj->datem), 'dayhour').'</td>';
			print '<td class="right">'.$staticbom->getLibStatut(5).'</td>';
			print '</tr>';
			$i++;
		}
	} else {
		print '<tr class="oddeven">';
		print '<td><span class="opacitymedium">' . $langs->trans("None") . '</span></td>';
		print '</tr>';
	}
	print "</table></div>";
	print "<br>";
}
else
{
	dol_print_error($db);
}



print '</div></div></div>';

$parameters = array('type' => $type, 'user' => $user);
$reshook = $hookmanager->executeHooks('dashboardMRP', $parameters, $object); // Note that $action and $object may have been modified by hook

// End of page
llxFooter();
$db->close();
