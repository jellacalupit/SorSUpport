Add-Type -AssemblyName System.Drawing
$out = Join-Path $PSScriptRoot '../docs/diagrams'
[IO.Directory]::CreateDirectory($out) | Out-Null
$W=3600; $H=3000
$bmp=[Drawing.Bitmap]::new($W,$H)
$bmp.SetResolution(300,300)
$g=[Drawing.Graphics]::FromImage($bmp)
$g.Clear([Drawing.Color]::White)
$g.SmoothingMode='AntiAlias'; $g.TextRenderingHint='AntiAliasGridFit'
$pen=[Drawing.Pen]::new([Drawing.Color]::FromArgb(90,90,90),2)
$black=[Drawing.SolidBrush]::new([Drawing.Color]::Black)
$white=[Drawing.SolidBrush]::new([Drawing.Color]::White)
$font=[Drawing.Font]::new('Arial',19,[Drawing.FontStyle]::Regular,[Drawing.GraphicsUnit]::Pixel)
$bold=[Drawing.Font]::new('Arial',26,[Drawing.FontStyle]::Bold,[Drawing.GraphicsUnit]::Pixel)
$title=[Drawing.Font]::new('Arial',36,[Drawing.FontStyle]::Bold,[Drawing.GraphicsUnit]::Pixel)
$svg=[Text.StringBuilder]::new()
[void]$svg.AppendLine('<svg xmlns="http://www.w3.org/2000/svg" width="3600" height="3000" viewBox="0 0 3600 3000"><rect width="3600" height="3000" fill="white"/><defs><marker id="arrow" markerWidth="10" markerHeight="8" refX="9" refY="4" orient="auto"><path d="M0,0 L10,4 L0,8 Z" fill="#555"/></marker></defs>')
function Txt($text,$x,$y,$size=19,$strong=$false,$center=$false) {
  $f=$font; if($strong){$f=$bold}; if($size -eq 36){$f=$title}
  $lines=$text -split "`n"; $dy=0
  foreach($line in $lines){
    $xx=$x; if($center){$xx-=$g.MeasureString($line,$f).Width/2}
    $g.DrawString($line,$f,$black,[single]$xx,[single]($y+$dy))
    $escaped=[Security.SecurityElement]::Escape($line)
    $anchor=if($center){'middle'}else{'start'}; $weight=if($strong){'bold'}else{'normal'}
    [void]$svg.AppendLine("<text x='$x' y='$($y+$dy+$size)' text-anchor='$anchor' font-family='Arial,sans-serif' font-size='$size' font-weight='$weight'>$escaped</text>")
    $dy+=($size+7)
  }
}
$nodes=@{}
function Node($id,$name,$x,$y,$w,$h,$kind){$nodes[$id]=@{id=$id;name=$name;x=$x;y=$y;w=$w;h=$h;kind=$kind}}
$names=@('Account Management','User Authentication','System Configuration','Complaint Submission','Ticket Review and`nClassification','Ticket Routing and`nAssignment','Ticket Handling, Escalation,`nand Closure','Notification and Reminders','Analytics and`nReport Generation')
for($i=1;$i -le 9;$i++){Node "P$i" ($names[$i-1].Replace('`n',"`n")) 1480 (210+($i-1)*285) 500 170 'process'}
Node 'S' 'Students' 70 480 400 110 'entity'
Node 'A' "SDS Administrator" 70 1230 400 110 'entity'
Node 'R' 'Recipients' 70 2010 400 110 'entity'
Node 'B' "Email Service`n(Brevo)" 70 2570 400 120 'entity'
$stores=@('User Accounts','Complaints and Tickets','Ticket Threads`nand Messages','Audit Logs','System Configuration','Notification Records')
for($i=1;$i -le 6;$i++){Node "D$i" ($stores[$i-1].Replace('`n',"`n")) 3100 (300+($i-1)*450) 430 130 'store'}
$edges=[Collections.Generic.List[object]]::new()
function E($from,$to,$label,$detail){$edges.Add(@{from=$from;to=$to;label=$label;detail=$detail;id=('F{0:D2}' -f ($edges.Count+1))})}
E A P1 'Account inputs' 'Student enrollment roster, Recipient uploads, and manual account-management instructions.'
E P1 A 'Account results' 'Account confirmation, upload results, and error report.'
E D1 P1 'Existing accounts' 'Existing Student and Recipient account records.'
E D5 P1 'Validation settings' 'Colleges, offices, programs, and designations used for validation.'
E P1 D1 'Account updates' 'Created, updated, reactivated, and deactivated accounts; no deletion.'
E P1 D4 'Account audit' 'Account-management action records.'
E S P2 'Authentication inputs' 'Login credentials, verification response, initial password and recovery/reset inputs.'
E R P2 'Authentication inputs' 'Login credentials, verification response, initial password and recovery/reset inputs.'
E A P2 'Administrator credentials' 'Administrator login credentials and administrator password-setup inputs.'
E P2 S 'Authentication prompts' 'Verification and password-setup/reset prompts and authentication results.'
E P2 R 'Authentication prompts' 'Verification and password-setup/reset prompts and authentication results.'
E D1 P2 'Account data' 'Stored credentials and account verification/activation information.'
E P2 D1 'Authentication updates' 'Verified/active status and valid password changes.'
E A P3 'Configuration data' 'Organizational settings, categories, identity settings, Recipients, escalation paths, reminders and working calendar.'
E P3 D5 'Settings' 'Maintained system configuration.'
E P3 D4 'Configuration audit' 'Configuration change records.'
E S P4 'Complaint submission' 'Complaint data, declaration, identity choice and attachments.'
E D5 P4 'Active categories' 'Available complaint categories and relevant settings.'
E P4 S 'Category list' 'Categories displayed in the complaint form.'
E P4 D2 'New complaint / ticket' 'Complaint and Submitted ticket record, including attachment references.'
E P4 D4 'Submission audit' 'Complaint submission action record.'
E A P5 'Review decisions' 'Clarification request; closure decision and reason; classification and jurisdiction; informational forwarding details.'
E S P5 'Clarification reply' 'Student clarification response returning the ticket to review.'
E D2 P5 'Ticket details' 'Submitted ticket and complaint details for review.'
E P5 D2 'Review updates' 'Status, classification, closure, receiving Recipient and forwarding date.'
E P5 D3 'Clarification thread' 'Thread opened for clarification.'
E P5 D4 'Review audit' 'Review, classification, forwarding and closure action records.'
E A P6 'Assignment instructions' 'Direct handling, assignment and reassignment instructions.'
E D2 P6 'Ticket details' 'Needs Resolution ticket details.'
E D5 P6 'Routing settings' 'Configured primary/suggested Recipients and escalation hierarchy.'
E D1 P6 'Recipient eligibility' 'Recipient role and active account status.'
E P6 D2 'Assignment updates' 'Assignment and handler details.'
E P6 D3 'Thread access' 'Assigned Recipient access to the ticket conversation.'
E P6 D4 'Assignment audit' 'Assignment and reassignment action records.'
E A P7 'Handling inputs' 'Status, escalation, referral and outcome records; closure decisions; messages and attachments.'
E R P7 'Recipient responses' 'Acknowledgments, messages and attachments, return-to-SDS reasons, resolution data.'
E S P7 'Student responses' 'Messages and attachments, acceptance/further action, withdrawal/referral-withdrawal, update/escalation requests, ratings.'
E P7 A 'Ticket / thread data' 'Authorized ticket details, status and attention information, and thread messages.'
E P7 R 'Ticket / thread data' 'Authorized assigned or forwarded ticket details and thread messages.'
E P7 S 'Ticket / thread data' 'Ticket status updates and authorized thread messages.'
E D2 P7 'Current ticket' 'Current ticket status and handling information.'
E D5 P7 'Escalation settings' 'Configured escalation paths.'
E D3 P7 'Thread content' 'Stored messages and attachments.'
E P7 D3 'Thread updates' 'Messages, attachments and thread locking on resolution or closure; content retained.'
E P7 D2 'Handling updates' 'Status, handler, referral, outcome, resolution, closure and rating updates.'
E P7 D4 'Handling audit' 'Records of every handling action.'
E D1 P8 'Email addresses' 'Registered email addresses.'
E D2 P8 'Reminder ticket data' 'Ticket information used to evaluate reminders.'
E D5 P8 'Reminder settings' 'Reminder configuration and working-calendar data.'
E D4 P8 'Activity records' 'Relevant activity records used to evaluate reminders.'
E D6 P8 'Retry records' 'Existing ticket notification dispatch and retry information.'
E P8 D6 'Notification records' 'Ticket email records, dispatch status and retry details.'
E P8 D4 'Dispatch audit' 'Applicable email dispatch and failure events.'
E P8 B 'Outbound email requests' 'Account verification/recovery, ticket notifications and reminders.'
E B P8 'Transport result' 'Transport success or failure, not confirmation of receipt or reading.'
E P8 S 'Notification records' 'Relevant Student notification information.'
E P8 R 'Notification records' 'Relevant Recipient notification information.'
E P8 A 'Notification records' 'Relevant SDS notification information including new ticket alerts.'
E A P9 'Report parameters' 'Report requests, filters and dashboard view requests.'
E P9 A 'Dashboard / reports' 'Aggregate dashboard data and generated PDF/Excel reports.'
E D2 P9 'Ticket metrics' 'Volumes, categories, classifications/statuses, resolution rates/times, escalations, college/program breakdowns and ratings.'
E D4 P9 'Audit data' 'Audit records for oversight and reporting.'
E D1 P9 'Profile data' 'Permitted account and profile information.'
E D5 P9 'Reporting settings' 'Categories and organizational configuration.'
foreach($i in @(1,2,4,5,6,7)){E "P$i" P8 "Notification request" "Account or ticket notification request from P$i; P8 handles dispatch."}
function Curve($x1,$y1,$c1x,$c1y,$c2x,$c2y,$x2,$y2){
 $g.DrawBezier($pen,[single]$x1,[single]$y1,[single]$c1x,[single]$c1y,[single]$c2x,[single]$c2y,[single]$x2,[single]$y2)
 $angle=[Math]::Atan2(($y2-$c2y),($x2-$c2x));$len=13;$spread=0.42
 $pts=[Drawing.PointF[]]@([Drawing.PointF]::new($x2,$y2),[Drawing.PointF]::new(($x2-$len*[Math]::Cos($angle-$spread)),($y2-$len*[Math]::Sin($angle-$spread))),[Drawing.PointF]::new(($x2-$len*[Math]::Cos($angle+$spread)),($y2-$len*[Math]::Sin($angle+$spread))))
 $g.FillPolygon($black,$pts)
 [void]$svg.AppendLine("<path d='M$x1,$y1 C$c1x,$c1y $c2x,$c2y $x2,$y2' fill='none' stroke='#555' stroke-width='2' marker-end='url(#arrow)'/>")
}
$labels=[Collections.Generic.List[object]]::new()
foreach($p in 1..9){
 $processId="P$p";$n=$nodes[$processId]
 foreach($side in @('left','right')){
  $list=@($edges | Where-Object {($_.from -eq $processId -or $_.to -eq $processId) -and $( $other=if($_.from -eq $processId){$_.to}else{$_.from}; if($side -eq 'left'){$nodes[$other].kind -eq 'entity'}else{$nodes[$other].kind -eq 'store'})})
  for($j=0;$j -lt $list.Count;$j++){
   $e=$list[$j];$other=if($e.from -eq $processId){$e.to}else{$e.from};$t=$nodes[$other]
   $py=$n.y+12+($j+0.5)*($n.h-24)/[Math]::Max(1,$list.Count)
   $all=@($edges | Where-Object {$_.from -eq $other -or $_.to -eq $other});$ix=[array]::IndexOf($all,$e)
   $ty=$t.y+10+($ix+0.5)*($t.h-20)/$all.Count
   if($side -eq 'left'){$px=$n.x;$tx=$t.x+$t.w;$cx1=1180;$cx2=720;$lx=1120}
   else{$px=$n.x+$n.w;$tx=$t.x;$cx1=2400;$cx2=2800;$lx=2040}
   if($e.from -eq $processId){Curve $px $py $cx1 $py $cx2 $ty $tx $ty}
   else{Curve $tx $ty $cx2 $ty $cx1 $py $px $py}
   $labels.Add(@{text="$($e.id) $($e.label)";x=$lx;y=($py-11)})
  }
 }
}
# Distinct numbered notification routes join their actual destination, P8.
$notify=@($edges | Where-Object {$_.from -match '^P' -and $_.to -eq 'P8'})
for($j=0;$j -lt $notify.Count;$j++){
 $e=$notify[$j];$a=$nodes[$e.from];$b=$nodes.P8;$x=1995+$j*12
 $y1=$a.y+$a.h;$y2=$b.y
 $endX=1700+$j*35; $bendY=$y2-22-$j*8
 $points=[Drawing.PointF[]]@([Drawing.PointF]::new(1980,($y1-10)),[Drawing.PointF]::new($x,($y1-10)),[Drawing.PointF]::new($x,$bendY),[Drawing.PointF]::new($endX,$bendY),[Drawing.PointF]::new($endX,$y2))
 $g.DrawLines($pen,$points)
 $g.FillPolygon($black,[Drawing.PointF[]]@([Drawing.PointF]::new($endX,$y2),[Drawing.PointF]::new(($endX-5),($y2-13)),[Drawing.PointF]::new(($endX+5),($y2-13))))
 [void]$svg.AppendLine("<polyline points='1980,$($y1-10) $x,$($y1-10) $x,$bendY $endX,$bendY $endX,$y2' fill='none' stroke='#555' stroke-width='2' marker-end='url(#arrow)'/>")
 $labels.Add(@{text=$e.id;x=1985;y=$y1-5})
}
foreach($l in $labels){$sz=$g.MeasureString($l.text,$font);$g.FillRectangle($white,[single]$l.x,[single]$l.y,[single]$sz.Width,[single]$sz.Height);[void]$svg.AppendLine("<rect x='$($l.x)' y='$($l.y)' width='$($sz.Width)' height='$($sz.Height)' fill='white'/>");Txt $l.text $l.x $l.y}
foreach($n in $nodes.Values){
 $rect=[Drawing.RectangleF]::new($n.x,$n.y,$n.w,$n.h)
 $outline=[Drawing.Pen]::new([Drawing.Color]::Black,3)
 $g.FillRectangle($white,$rect)
 if($n.kind -eq 'process'){
  $path=[Drawing.Drawing2D.GraphicsPath]::new();$r=48
  $path.AddArc($n.x,$n.y,$r,$r,180,90);$path.AddArc(($n.x+$n.w-$r),$n.y,$r,$r,270,90);$path.AddArc(($n.x+$n.w-$r),($n.y+$n.h-$r),$r,$r,0,90);$path.AddArc($n.x,($n.y+$n.h-$r),$r,$r,90,90);$path.CloseFigure();$g.DrawPath($outline,$path)
 }elseif($n.kind -eq 'store'){
  $g.DrawLines($outline,[Drawing.PointF[]]@([Drawing.PointF]::new(($n.x+$n.w),$n.y),[Drawing.PointF]::new($n.x,$n.y),[Drawing.PointF]::new($n.x,($n.y+$n.h)),[Drawing.PointF]::new(($n.x+$n.w),($n.y+$n.h))))
  $g.DrawLine($outline,($n.x+65),$n.y,($n.x+65),($n.y+$n.h))
 }else{$g.DrawRectangle($outline,$n.x,$n.y,$n.w,$n.h)}
 $rx=if($n.kind -eq 'process'){24}else{0}
 if($n.kind -eq 'store'){
  [void]$svg.AppendLine("<rect x='$($n.x)' y='$($n.y)' width='$($n.w)' height='$($n.h)' fill='white'/><path d='M$($n.x+$n.w),$($n.y) H$($n.x) V$($n.y+$n.h) H$($n.x+$n.w) M$($n.x+65),$($n.y) V$($n.y+$n.h)' fill='none' stroke='black' stroke-width='3'/>")
 }else{[void]$svg.AppendLine("<rect x='$($n.x)' y='$($n.y)' width='$($n.w)' height='$($n.h)' rx='$rx' fill='white' stroke='black' stroke-width='3'/>")}
 if($n.kind -eq 'process'){Txt $n.id ($n.x+$n.w/2) ($n.y+17) 26 $true $true;Txt $n.name ($n.x+$n.w/2) ($n.y+62) 26 $true $true}
 elseif($n.kind -eq 'store'){Txt $n.id ($n.x+32) ($n.y+48) 26 $true $true;Txt $n.name ($n.x+65+($n.w-65)/2) ($n.y+35) 26 $true $true}
 else{Txt $n.name ($n.x+$n.w/2) ($n.y+27) 26 $true $true}
}
Txt 'SorSUpport — Level 1 Data Flow Diagram' 1800 30 36 $true $true
Txt 'Four external entities · Nine processes · Six data stores' 1800 90 19 $false $true
Txt 'Arrowheads indicate data direction. F01–F70 identify flows in the accompanying reference.' 1800 2840 19 $false $true
Txt 'F65–F70: notification requests from P1, P2, P4, P5, P6 and P7 to P8. Crossings do not indicate junctions.' 1800 2880 19 $false $true
Txt 'Processing rules remain in the verified process descriptions. Brevo transport success does not confirm receipt or reading.' 1800 2920 19 $false $true
[void]$svg.AppendLine('</svg>')
$svg.ToString() | Set-Content -LiteralPath (Join-Path $out 'level1-verified.svg') -Encoding utf8
$bmp.Save((Join-Path $out 'level1-verified.png'),[Drawing.Imaging.ImageFormat]::Png)
$md=@('# SorSUpport Level 1 flow reference','','Source of truth: the verified nine-process description supplied by the author. Each entity and store appears once. Crossings are not junctions.','','| ID | Source | Destination | Diagram label | Data represented |','|---|---|---|---|---|')
foreach($e in $edges){$md+="| $($e.id) | $($e.from) | $($e.to) | $($e.label) | $($e.detail) |"}
$md+=@('','Notes: D2 stores complaint/ticket data and attachment references; the prose does not explicitly name a separate complaint-attachment store. D3 stores thread attachments. P6 thread-access data represents the described access grant. Notification request routes are distinct from data-store flows. P9 has no data-store writes. P8 never writes ticket status or handler to D2. Account email queue implementation remains in the prose; D6 represents ticket notification records.','')
$md | Set-Content -LiteralPath (Join-Path $out 'level1-flow-reference.md') -Encoding utf8
$g.Dispose();$bmp.Dispose()
Write-Output "Exported $($edges.Count) directed flows to $out"

