<p style='padding-top:20px;'>
</p>
<h1>
<?php 
echo $settings['companytitle'];
?>
</h1>
<br>
<div style='text-align:left;width:90%;'>
<?php te("Tips for new users:");?><br>
<ol>
<li><?php te('First, add one or more Agents (vendors, S/W &amp; H/W manufacturers, contractors) by clicking the "+" next to the "Agents" on the left.');?>
<li><?php te('Then, add Some item types (PC, Printer, Switch FC, etc) and optionally contract types (License, S&amp;M,...) using the "Item Types" menu');?>
<li><?php te("Then, you can start adding Items, Software and Contracts.");?>
</ol>

<?php te("Menu help:");?>
<ul>
<li><b><?php te("Items");?></b>: <?php te("all physical assets. PCs, printers, servers, phones, tape libraries, video players, etc.");?> 
<?php te("Items can be associated with other items, and invoices. You may also add relevant files (manuals, offers, etc)");?></li>
<li><b><?php te("Invoices");?></b>: <?php te("proofs of purchase for hardware, software, contracts, etc. These are different from other files/documents (manuals, offers, etc) because they contain extra metadata like vendor, buyer, dates etc");?> </li>
<li><b><?php te("Software");?></b>: <?php te("all software metadata. You may associate software with items in this menu (e.g. assign a software to multiple PCs)");?></li>
<li><b><?php te("Agents");?></b>: <?php te("agents are entities like Vendors, S/W Manufacturers, H/W Manufacturers, Contractors, and Buyers");?> 
<li><b><?php te("Racks");?></b>: <?php te("here you may enter rack data + view rack layouts. Items are assigned to racks based on their rackmountable,rack and rack-position properties.");?></li>
<li><b><?php te("Contracts");?></b>: <?php te("enter contracts like support&amp;maintenance,leases etc. Contracts can be associated with Items and Software and have related documents and invoices. Contract events are also kept here.");?></li>
<li><b><?php te("Files");?></b>: <?php te("you may edit file data here for files that were previously uploaded through the Items, Software, Invoices or Contract file upload tabs. You may also upload new files (except invoices) and relate them to more Items, Software or Contracts.");?></li>
</div>
<br>
<div style='text-align:left;font-size:0.8em;'>
<?php te("This project contains icons from:");?><br>
WebIconSet.com<br>
everaldo.com/crystal<br>
lazycrazy.deviantart.com/<br>
famfam
</div>
