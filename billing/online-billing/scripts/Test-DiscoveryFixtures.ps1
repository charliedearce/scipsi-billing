$ErrorActionPreference = 'Stop'
$fixturePath = Join-Path (Split-Path $PSScriptRoot -Parent) 'fixtures/legacy-calculations.json'
$fixtures = Get-Content -LiteralPath $fixturePath -Raw | ConvertFrom-Json
function D($value) { return [decimal]::Parse([string]$value, [Globalization.CultureInfo]::InvariantCulture) }
function T2([decimal]$value) { return [decimal]::Truncate($value * 100) / 100 }
$ids = @{}
$assertions = 0
foreach ($case in $fixtures.cases) {
    if ($ids.ContainsKey($case.id)) { throw "Duplicate ID: $($case.id)" }
    $ids[$case.id] = $true
    $v = $case.input
    $actual = @{}
    switch ($case.kind) {
        'line' {
            $gross = D $v.gross
            $ppa = if ($v.ppa) { T2 ($gross * (D $v.ppa_rate)) } else { [decimal]0 }
            $discount = T2 ($gross * (1 - (D $v.ppa_rate)) * (D $v.marker_percent) / 100)
            $baseNet = $gross - $ppa
            $tax = if ($v.vat) { T2 (($baseNet - $discount) * (D $v.vat_rate)) } else { [decimal]0 }
            $actual = @{ ppa=$ppa; discount=$discount; net=($baseNet-$discount); tax=$tax; scipsi=(T2 ((T2 ($baseNet+$tax))-$discount)); charge=(T2 ($gross+$tax)) }
        }
        'quantity' {
            $rate = D $v.rate
            $baseGross = (D $v.quantity) * $rate
            $gross = $baseGross * (D $v.factor)
            $rate = $rate * (D $v.factor)
            if ($v.fuel) {
                $gross = [decimal]::Floor($gross)
                $rate = [decimal]::Round($rate, 2, [MidpointRounding]::AwayFromZero)
            }
            $actual = @{base_gross=$baseGross; gross=$gross; rate=$rate}
        }
        'full_receipt' {
            $withholding = if ($v.withholding) { ((D $v.amount)-(D $v.vat)) * (D $v.withholding_rate) } else { [decimal]0 }
            $actual = @{withholding=$withholding; net=((D $v.amount)-$withholding)}
        }
        'partial_receipt' {
            $paid = (D $v.cash)+(D $v.withholding)
            $actual = @{balance=((D $v.amount)-(D $v.cash)); vat=($paid / [decimal]1.12 * [decimal]0.12); indicator=$(if ((D $v.amount) -le $paid) {'F'} else {'P'})}
        }
        'statement' {
            $overdue=(D $v.balance)+(D $v.finance_charge)-(D $v.payment)
            $actual=@{overdue=$overdue; amount_due=($overdue+(D $v.current_charge))}
        }
        'period' {
            $date = [DateTime]::ParseExact($v.date,'yyyy-MM-dd',[Globalization.CultureInfo]::InvariantCulture)
            $month=$date.Month
            if ($date.Day -gt 25 -and $month -lt 12) { $month++ }
            $actual=@{period=('{0}{1:000}' -f $date.Year,$month); reference=('RB{0:00}0{1}' -f $month,[long]$v.number)}
        }
        default { throw "Unsupported fixture kind: $($case.kind)" }
    }
    foreach ($expected in $case.expected.PSObject.Properties) {
        $name=$expected.Name
        if (-not $actual.ContainsKey($name)) { throw "Missing result $name in $($case.id)" }
        if ($name -in @('indicator','period','reference')) { $equal=([string]$actual[$name] -ceq [string]$expected.Value) }
        else { $equal=($actual[$name] -eq (D $expected.Value)) }
        if (-not $equal) { throw "Fixture $($case.id), $name expected $($expected.Value), got $($actual[$name])" }
        $assertions++
    }
}
Write-Output "$($fixtures.cases.Count) synthetic source-derived fixtures / $assertions arithmetic assertions passed."
Write-Output 'This checks reference arithmetic only; it does not execute VB event handlers, SQL writes, or approved target business rules.'
