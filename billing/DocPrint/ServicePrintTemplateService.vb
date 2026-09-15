Imports System.Configuration
Imports System.Data
Imports System.Data.SqlClient
Imports System.Drawing
Imports System.Drawing.Printing
Imports System.Globalization
Imports System.IO
Imports System.Windows.Forms
Imports DevExpress.XtraReports.UI

Public NotInheritable Class ServicePrintTemplateService
    Private Const TemplateFileName As String = "ServiceBilling.repx"
    Private Const TemplateDirectoryName As String = "SCIPSI Billing\Templates"

    Private Sub New()
    End Sub

    Public Shared ReadOnly Property TemplatePath As String
        Get
            Dim configuredPath As String = ConfigurationManager.AppSettings("ServicePrintTemplatePath")
            If Not String.IsNullOrWhiteSpace(configuredPath) Then
                Return Environment.ExpandEnvironmentVariables(configuredPath)
            End If

            Return Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData), TemplateDirectoryName, TemplateFileName)
        End Get
    End Property

    Public Shared Sub DesignTemplate(ByVal owner As IWin32Window)
        Try
            Using data As DataSet = ServicePrintDataProvider.CreateSampleData()
                Using report As XtraReport = CreateBoundReport(data, True)
                    Using designTool As New ReportDesignTool(report)
                        designTool.ShowDesignerDialog()
                    End Using

                    If MessageBox.Show(owner,
                                       "Save this layout as the active Service Billing template?" & Environment.NewLine & Environment.NewLine & TemplatePath,
                                       "Service Print Designer",
                                       MessageBoxButtons.YesNo,
                                       MessageBoxIcon.Question) = DialogResult.Yes Then
                        SaveTemplate(report)
                        MessageBox.Show(owner, "Service Billing template saved.", "Service Print Designer", MessageBoxButtons.OK, MessageBoxIcon.Information)
                    End If
                End Using
            End Using
        Catch ex As Exception
            MessageBox.Show(owner, "Unable to open or save the Service Billing template designer." & Environment.NewLine & ex.Message,
                            "Service Print Designer", MessageBoxButtons.OK, MessageBoxIcon.Error)
        End Try
    End Sub

    Public Shared Function PrintBill(ByVal billNumber As String, ByVal owner As IWin32Window) As Boolean
        If String.IsNullOrWhiteSpace(billNumber) Then
            MessageBox.Show(owner, "A bill number is required before printing.", "Print Service Bill", MessageBoxButtons.OK, MessageBoxIcon.Warning)
            Return False
        End If

        Try
            Using data As DataSet = ServicePrintDataProvider.LoadBill(billNumber)
                Using report As XtraReport = CreateBoundReport(data, True)
                    Using printTool As New ReportPrintTool(report)
                        Dim printRequested As Boolean
                        If DirectPrintingEnabled() Then
                            printTool.Print()
                            printRequested = True
                        Else
                            Dim result As Nullable(Of Boolean) = printTool.PrintDialog(owner)
                            printRequested = result.HasValue AndAlso result.Value
                        End If

                        If printRequested Then
                            MarkBillPrinted(billNumber)
                        End If

                        Return printRequested
                    End Using
                End Using
            End Using
        Catch ex As Exception
            MessageBox.Show(owner, "Unable to print Service Bill " & billNumber & "." & Environment.NewLine & ex.Message,
                            "Print Service Bill", MessageBoxButtons.OK, MessageBoxIcon.Error)
            Return False
        End Try
    End Function

    Private Shared Function CreateBoundReport(ByVal data As DataSet, ByVal warnOnInvalidTemplate As Boolean) As XtraReport
        Dim report As XtraReport = ServicePrintReportFactory.CreateDefaultReport()

        If File.Exists(TemplatePath) Then
            Try
                report.LoadLayout(TemplatePath)
            Catch ex As Exception
                report.Dispose()
                report = ServicePrintReportFactory.CreateDefaultReport()
                If warnOnInvalidTemplate Then
                    MessageBox.Show("The saved Service Billing template could not be loaded. The default layout will be opened instead." & Environment.NewLine & ex.Message,
                                    "Service Print Designer", MessageBoxButtons.OK, MessageBoxIcon.Warning)
                End If
            End Try
        End If

        BindData(report, data)
        Return report
    End Function

    Private Shared Sub BindData(ByVal report As XtraReport, ByVal data As DataSet)
        report.DataSource = data
        report.DataMember = "Bill"
        report.ScriptsSource = String.Empty
        BindItemBands(report.Bands, data)
    End Sub

    Private Shared Sub BindItemBands(ByVal bands As BandCollection, ByVal data As DataSet)
        For Each band As Band In bands
            Dim detailBand As DetailReportBand = TryCast(band, DetailReportBand)
            If detailBand Is Nothing Then
                Continue For
            End If

            If String.Equals(detailBand.Name, "ItemsBand", StringComparison.OrdinalIgnoreCase) OrElse
               (detailBand.DataMember IsNot Nothing AndAlso detailBand.DataMember.EndsWith("BillItems", StringComparison.OrdinalIgnoreCase)) Then
                detailBand.DataSource = data
                detailBand.DataMember = "Bill.BillItems"
            End If

            BindItemBands(detailBand.Bands, data)
        Next
    End Sub

    Private Shared Sub SaveTemplate(ByVal report As XtraReport)
        Dim directoryPath As String = Path.GetDirectoryName(TemplatePath)
        If String.IsNullOrWhiteSpace(directoryPath) Then
            Throw New InvalidOperationException("The Service Billing template path must include a directory.")
        End If

        Directory.CreateDirectory(directoryPath)
        If File.Exists(TemplatePath) Then
            Dim backupPath As String = Path.Combine(directoryPath,
                                                    "ServiceBilling-" & DateTime.Now.ToString("yyyyMMdd-HHmmss", CultureInfo.InvariantCulture) & ".repx.backup")
            File.Copy(TemplatePath, backupPath, False)
        End If

        report.ScriptsSource = String.Empty
        report.DataSource = Nothing
        report.SaveLayout(TemplatePath)
    End Sub

    Private Shared Function DirectPrintingEnabled() As Boolean
        Using connection As New SqlConnection(ConfigurationManager.ConnectionStrings("ConString").ConnectionString)
            Using command As New SqlCommand("SELECT TOP (1) myprint FROM tbl_settings", connection)
                connection.Open()
                Dim value As Object = command.ExecuteScalar()
                Return value IsNot Nothing AndAlso value IsNot DBNull.Value AndAlso
                       String.Equals(Convert.ToString(value, CultureInfo.InvariantCulture), "YES", StringComparison.OrdinalIgnoreCase)
            End Using
        End Using
    End Function

    Private Shared Sub MarkBillPrinted(ByVal billNumber As String)
        Using connection As New SqlConnection(ConfigurationManager.ConnectionStrings("ConString").ConnectionString)
            Using command As New SqlCommand("UPDATE tbl_bill_trans SET bt_indiprint = 'Y' WHERE bt_bill_num = @bill_num", connection)
                command.Parameters.Add("@bill_num", SqlDbType.VarChar, 20).Value = billNumber
                connection.Open()
                command.ExecuteNonQuery()
            End Using
        End Using
    End Sub
End Class

Friend NotInheritable Class ServicePrintDataProvider
    Private Sub New()
    End Sub

    Public Shared Function LoadBill(ByVal billNumber As String) As DataSet
        Dim data As DataSet = CreateSchema()
        Dim bill As DataTable = data.Tables("Bill")
        Dim items As DataTable = data.Tables("Items")

        Using connection As New SqlConnection(ConfigurationManager.ConnectionStrings("ConString").ConnectionString)
            connection.Open()

            Using command As New SqlCommand("SELECT TOP (1) bt_bill_num, bt_acc_num, bt_account, bt_vessel, bt_voyage, bt_particulars, " &
                                            "bt_route, bt_type, bt_date, bt_employee, bt_total, bt_ppa, bt_disc, bt_net, bt_vat, bt_scipsi, " &
                                            "bt_bstyle, bt_tin, bt_baddress, bt_per, bt_ref " &
                                            "FROM tbl_bill_trans WHERE bt_bill_num = @bill_num", connection)
                command.Parameters.Add("@bill_num", SqlDbType.VarChar, 20).Value = billNumber
                Using reader As SqlDataReader = command.ExecuteReader()
                    If Not reader.Read() Then
                        Throw New InvalidOperationException("The saved bill was not found. Save the bill before printing it.")
                    End If

                    Dim movementType As String = ReadString(reader, "bt_type")
                    Dim movementDisplay As String = movementType
                    If String.Equals(movementType, "I", StringComparison.OrdinalIgnoreCase) Then
                        movementDisplay = "IN"
                    ElseIf String.Equals(movementType, "O", StringComparison.OrdinalIgnoreCase) Then
                        movementDisplay = "OUT"
                    End If
                    Dim row As DataRow = bill.NewRow()
                    row("BillNumber") = ReadString(reader, "bt_bill_num")
                    row("AccountNumber") = ReadString(reader, "bt_acc_num")
                    row("CustomerName") = ReadString(reader, "bt_account")
                    row("Vessel") = ReadString(reader, "bt_vessel")
                    row("Voyage") = ReadString(reader, "bt_voyage")
                    row("Particulars") = ReadString(reader, "bt_particulars")
                    row("Route") = ReadString(reader, "bt_route")
                    row("MovementType") = movementType
                    row("VesselText") = "VESSEL: " & ReadString(reader, "bt_vessel")
                    row("VoyageText") = "VOYAGE: " & ReadString(reader, "bt_voyage")
                    row("MovementText") = "IN/OUT: " & movementDisplay
                    row("BillDate") = ReadDate(reader, "bt_date")
                    row("EmployeeName") = ReadString(reader, "bt_employee")
                    row("TotalCharges") = ReadDecimal(reader, "bt_total")
                    row("PpaShare") = ReadDecimal(reader, "bt_ppa")
                    row("Discount") = ReadDecimal(reader, "bt_disc")
                    row("ScipsiShare") = ReadDecimal(reader, "bt_net")
                    row("VatAmount") = ReadDecimal(reader, "bt_vat")
                    row("TotalAmount") = ReadDecimal(reader, "bt_scipsi")
                    row("BusinessStyle") = ReadString(reader, "bt_bstyle")
                    row("Tin") = ReadString(reader, "bt_tin")
                    row("Address") = ReadString(reader, "bt_baddress")
                    row("Period") = ReadString(reader, "bt_per")
                    row("Reference") = ReadString(reader, "bt_ref")
                    bill.Rows.Add(row)
                End Using
            End Using

            Using command As New SqlCommand("SELECT it_qty, it_unit, it_service, it_scode, it_ccode, it_cargo, it_rate, it_gross, " &
                                            "it_ppa, it_net, it_tax, it_scipsi, it_charge, it_disc " &
                                            "FROM tbl_item_trans WHERE it_bill_num = @bill_num ORDER BY it_id", connection)
                command.Parameters.Add("@bill_num", SqlDbType.VarChar, 20).Value = billNumber
                Using reader As SqlDataReader = command.ExecuteReader()
                    While reader.Read()
                        Dim row As DataRow = items.NewRow()
                        row("BillNumber") = billNumber
                        row("Quantity") = ReadDecimal(reader, "it_qty")
                        row("Unit") = ReadString(reader, "it_unit")
                        row("ServiceName") = ReadString(reader, "it_service")
                        row("ServiceCode") = ReadString(reader, "it_scode")
                        row("CargoCode") = ReadString(reader, "it_ccode")
                        row("CargoDescription") = ReadString(reader, "it_cargo")
                        row("Rate") = ReadDecimal(reader, "it_rate")
                        row("GrossAmount") = ReadDecimal(reader, "it_gross")
                        row("PpaShare") = ReadDecimal(reader, "it_ppa")
                        row("NetAmount") = ReadDecimal(reader, "it_net")
                        row("VatAmount") = ReadDecimal(reader, "it_tax")
                        row("ScipsiAmount") = ReadDecimal(reader, "it_scipsi")
                        row("ChargeAmount") = ReadDecimal(reader, "it_charge")
                        row("Discount") = ReadDecimal(reader, "it_disc")
                        items.Rows.Add(row)
                    End While
                End Using
            End Using
        End Using

        SetLineText(data)
        Return data
    End Function

    Public Shared Function CreateSampleData() As DataSet
        Dim data As DataSet = CreateSchema()
        Dim bill As DataRow = data.Tables("Bill").NewRow()
        bill("BillNumber") = "0000001234"
        bill("AccountNumber") = "11001-0001"
        bill("CustomerName") = "SAMPLE CUSTOMER"
        bill("Vessel") = "MV SAMPLE"
        bill("Voyage") = "V-001"
        bill("Particulars") = "Sample service billing particulars"
        bill("Route") = "D"
        bill("MovementType") = "I"
        bill("VesselText") = "VESSEL: MV SAMPLE"
        bill("VoyageText") = "VOYAGE: V-001"
        bill("MovementText") = "IN/OUT: IN"
        bill("BillDate") = Date.Today
        bill("EmployeeName") = "SAMPLE CASHIER"
        bill("TotalCharges") = 1120D
        bill("PpaShare") = 0D
        bill("Discount") = 20D
        bill("ScipsiShare") = 1000D
        bill("VatAmount") = 120D
        bill("TotalAmount") = 1100D
        bill("BusinessStyle") = "PORT SERVICES"
        bill("Tin") = "000-000-000"
        bill("Address") = "Sample address"
        bill("Period") = Date.Today.ToString("yyyyMM", CultureInfo.InvariantCulture)
        bill("Reference") = "SAMPLE-REF"
        data.Tables("Bill").Rows.Add(bill)

        AddSampleItem(data.Tables("Items"), "0000001234", 2D, "UNIT", "HANDLING SERVICE", "CARGO-01", 300D)
        AddSampleItem(data.Tables("Items"), "0000001234", 1D, "UNIT", "STORAGE SERVICE", "CARGO-02", 520D)
        SetLineText(data)
        Return data
    End Function

    Private Shared Sub AddSampleItem(ByVal table As DataTable, ByVal billNumber As String, ByVal quantity As Decimal,
                                     ByVal unit As String, ByVal serviceName As String, ByVal cargoCode As String,
                                     ByVal grossAmount As Decimal)
        Dim row As DataRow = table.NewRow()
        row("BillNumber") = billNumber
        row("Quantity") = quantity
        row("Unit") = unit
        row("ServiceName") = serviceName
        row("ServiceCode") = "40001"
        row("CargoCode") = cargoCode
        row("CargoDescription") = "Sample cargo"
        row("Rate") = grossAmount / quantity
        row("GrossAmount") = grossAmount
        row("PpaShare") = 0D
        row("NetAmount") = grossAmount
        row("VatAmount") = grossAmount * 0.12D
        row("ScipsiAmount") = grossAmount * 1.12D
        row("ChargeAmount") = grossAmount
        row("Discount") = 0D
        table.Rows.Add(row)
    End Sub

    Private Shared Function CreateSchema() As DataSet
        Dim data As New DataSet("ServiceBillingData")
        Dim bill As New DataTable("Bill")
        bill.Columns.Add("BillNumber", GetType(String))
        bill.Columns.Add("AccountNumber", GetType(String))
        bill.Columns.Add("CustomerName", GetType(String))
        bill.Columns.Add("Vessel", GetType(String))
        bill.Columns.Add("Voyage", GetType(String))
        bill.Columns.Add("Particulars", GetType(String))
        bill.Columns.Add("Route", GetType(String))
        bill.Columns.Add("MovementType", GetType(String))
        bill.Columns.Add("VesselText", GetType(String))
        bill.Columns.Add("VoyageText", GetType(String))
        bill.Columns.Add("MovementText", GetType(String))
        bill.Columns.Add("BillDate", GetType(DateTime))
        bill.Columns.Add("EmployeeName", GetType(String))
        bill.Columns.Add("TotalCharges", GetType(Decimal))
        bill.Columns.Add("PpaShare", GetType(Decimal))
        bill.Columns.Add("Discount", GetType(Decimal))
        bill.Columns.Add("ScipsiShare", GetType(Decimal))
        bill.Columns.Add("VatAmount", GetType(Decimal))
        bill.Columns.Add("TotalAmount", GetType(Decimal))
        bill.Columns.Add("BusinessStyle", GetType(String))
        bill.Columns.Add("Tin", GetType(String))
        bill.Columns.Add("Address", GetType(String))
        bill.Columns.Add("Period", GetType(String))
        bill.Columns.Add("Reference", GetType(String))
        bill.Columns.Add("QuantityLines", GetType(String))
        bill.Columns.Add("UnitLines", GetType(String))
        bill.Columns.Add("ServiceLines", GetType(String))
        bill.Columns.Add("CargoCodeLines", GetType(String))
        bill.Columns.Add("CargoDescriptionLines", GetType(String))
        bill.Columns.Add("RateLines", GetType(String))
        bill.Columns.Add("GrossLines", GetType(String))
        bill.PrimaryKey = New DataColumn() {bill.Columns("BillNumber")}

        Dim items As New DataTable("Items")
        items.Columns.Add("BillNumber", GetType(String))
        items.Columns.Add("Quantity", GetType(Decimal))
        items.Columns.Add("Unit", GetType(String))
        items.Columns.Add("ServiceName", GetType(String))
        items.Columns.Add("ServiceCode", GetType(String))
        items.Columns.Add("CargoCode", GetType(String))
        items.Columns.Add("CargoDescription", GetType(String))
        items.Columns.Add("Rate", GetType(Decimal))
        items.Columns.Add("GrossAmount", GetType(Decimal))
        items.Columns.Add("PpaShare", GetType(Decimal))
        items.Columns.Add("NetAmount", GetType(Decimal))
        items.Columns.Add("VatAmount", GetType(Decimal))
        items.Columns.Add("ScipsiAmount", GetType(Decimal))
        items.Columns.Add("ChargeAmount", GetType(Decimal))
        items.Columns.Add("Discount", GetType(Decimal))

        data.Tables.Add(bill)
        data.Tables.Add(items)
        data.Relations.Add("BillItems", bill.Columns("BillNumber"), items.Columns("BillNumber"), False)
        Return data
    End Function

    Private Shared Sub SetLineText(ByVal data As DataSet)
        Dim quantities As New List(Of String)()
        Dim units As New List(Of String)()
        Dim services As New List(Of String)()
        Dim cargoCodes As New List(Of String)()
        Dim cargoDescriptions As New List(Of String)()
        Dim rates As New List(Of String)()
        Dim grossAmounts As New List(Of String)()

        For Each row As DataRow In data.Tables("Items").Rows
            quantities.Add(Convert.ToDecimal(row("Quantity"), CultureInfo.InvariantCulture).ToString("0.##"))
            units.Add(Convert.ToString(row("Unit"), CultureInfo.InvariantCulture))
            services.Add(Convert.ToString(row("ServiceName"), CultureInfo.InvariantCulture))
            cargoCodes.Add(Convert.ToString(row("CargoCode"), CultureInfo.InvariantCulture))
            cargoDescriptions.Add(Convert.ToString(row("CargoDescription"), CultureInfo.InvariantCulture))
            rates.Add(Convert.ToDecimal(row("Rate"), CultureInfo.InvariantCulture).ToString("N2"))
            grossAmounts.Add(Convert.ToDecimal(row("GrossAmount"), CultureInfo.InvariantCulture).ToString("N2"))
        Next

        If data.Tables("Bill").Rows.Count > 0 Then
            Dim bill As DataRow = data.Tables("Bill").Rows(0)
            bill("QuantityLines") = String.Join(Environment.NewLine, quantities.ToArray())
            bill("UnitLines") = String.Join(Environment.NewLine, units.ToArray())
            bill("ServiceLines") = String.Join(Environment.NewLine, services.ToArray())
            bill("CargoCodeLines") = String.Join(Environment.NewLine, cargoCodes.ToArray())
            bill("CargoDescriptionLines") = String.Join(Environment.NewLine, cargoDescriptions.ToArray())
            bill("RateLines") = String.Join(Environment.NewLine, rates.ToArray())
            bill("GrossLines") = String.Join(Environment.NewLine, grossAmounts.ToArray())
        End If
    End Sub

    Private Shared Function ReadString(ByVal reader As SqlDataReader, ByVal columnName As String) As String
        Dim value As Object = reader(columnName)
        If value Is DBNull.Value Then Return String.Empty
        Return Convert.ToString(value, CultureInfo.InvariantCulture)
    End Function

    Private Shared Function ReadDecimal(ByVal reader As SqlDataReader, ByVal columnName As String) As Decimal
        Dim value As Object = reader(columnName)
        If value Is DBNull.Value Then Return 0D
        Return Convert.ToDecimal(value, CultureInfo.InvariantCulture)
    End Function

    Private Shared Function ReadDate(ByVal reader As SqlDataReader, ByVal columnName As String) As DateTime
        Dim value As Object = reader(columnName)
        If value Is DBNull.Value Then Return DateTime.Today
        Return Convert.ToDateTime(value, CultureInfo.InvariantCulture)
    End Function
End Class

Friend NotInheritable Class ServicePrintReportFactory
    Private Sub New()
    End Sub

    Public Shared Function CreateDefaultReport() As XtraReport
        Dim report As New XtraReport()
        report.Name = "ServiceBilling"
        report.DisplayName = "Service Billing"
        report.Dpi = 100.0!
        report.PaperKind = PaperKind.Letter
        report.Margins = New Margins(0, 0, 0, 0)
        report.ShowPrintMarginsWarning = False

        Dim topMargin As New TopMarginBand()
        topMargin.HeightF = 0.0!
        Dim detail As New DetailBand()
        detail.Name = "BillBand"
        detail.HeightF = 520.0!
        Dim bottomMargin As New BottomMarginBand()
        bottomMargin.HeightF = 0.0!
        report.Bands.AddRange(New Band() {topMargin, detail, bottomMargin})

        detail.Controls.AddRange(New XRControl() {
            CreateLabel("BillNumber", "Bill.BillNumber", New RectangleF(620, 96, 170, 20), 10.0!),
            CreateLabel("AccountNumber", "Bill.AccountNumber", New RectangleF(70, 110, 300, 18), 10.0!),
            CreateLabel("CustomerName", "Bill.CustomerName", New RectangleF(70, 125, 480, 18), 10.0!),
            CreateLabel("Address", "Bill.Address", New RectangleF(70, 140, 400, 35), 8.0!),
            CreateLabel("BusinessStyle", "Bill.BusinessStyle", New RectangleF(70, 170, 300, 15), 8.0!),
            CreateLabel("Tin", "Bill.Tin", New RectangleF(70, 185, 300, 15), 8.0!),
            CreateLabel("Particulars", "Bill.Particulars", New RectangleF(420, 170, 190, 30), 8.0!),
            CreateLabel("BillDate", "Bill.BillDate", New RectangleF(620, 170, 170, 18), 9.0!, "{0:MM/dd/yyyy}"),
            CreateLabel("VesselText", "Bill.VesselText", New RectangleF(80, 200, 225, 18), 10.0!),
            CreateLabel("VoyageText", "Bill.VoyageText", New RectangleF(320, 200, 220, 18), 10.0!),
            CreateLabel("MovementText", "Bill.MovementText", New RectangleF(600, 200, 190, 18), 10.0!),
            CreateLabel("QuantityLines", "Bill.QuantityLines", New RectangleF(5, 237, 60, 120), 10.0!),
            CreateLabel("UnitLines", "Bill.UnitLines", New RectangleF(70, 237, 55, 120), 10.0!),
            CreateLabel("ServiceLines", "Bill.ServiceLines", New RectangleF(130, 237, 250, 120), 10.0!),
            CreateLabel("CargoCodeLines", "Bill.CargoCodeLines", New RectangleF(390, 237, 140, 120), 10.0!),
            CreateLabel("RateLines", "Bill.RateLines", New RectangleF(540, 237, 75, 120), 10.0!),
            CreateLabel("GrossLines", "Bill.GrossLines", New RectangleF(620, 237, 170, 120), 10.0!),
            CreateStaticLabel("TotalChargesCaption", "TOTAL CHARGES:", New RectangleF(210, 370, 135, 18), 10.0!),
            CreateMoneyLabel("TotalCharges", "Bill.TotalCharges", New RectangleF(220, 383, 125, 18), 10.0!),
            CreateStaticLabel("PpaShareCaption", "PPA SHARE:", New RectangleF(210, 396, 135, 18), 10.0!),
            CreateMoneyLabel("PpaShare", "Bill.PpaShare", New RectangleF(220, 409, 125, 18), 10.0!),
            CreateStaticLabel("DiscountCaption", "TOTAL DISCOUNT:", New RectangleF(355, 370, 145, 18), 10.0!),
            CreateMoneyLabel("Discount", "Bill.Discount", New RectangleF(365, 383, 125, 18), 10.0!),
            CreateStaticLabel("ScipsiShareCaption", "SCIPSI SHARE:", New RectangleF(355, 396, 145, 18), 10.0!),
            CreateMoneyLabel("ScipsiShare", "Bill.ScipsiShare", New RectangleF(365, 409, 125, 18), 10.0!),
            CreateStaticLabel("VatCaption", "12% VAT:", New RectangleF(500, 370, 100, 18), 10.0!),
            CreateMoneyLabel("VatAmount", "Bill.VatAmount", New RectangleF(510, 383, 95, 18), 10.0!),
            CreateStaticLabel("TotalAmountCaption", "TOTAL AMOUNT:" & Environment.NewLine & "DUE TO SCIPSI:", New RectangleF(600, 370, 120, 38), 10.0!),
            CreateMoneyLabel("TotalAmount", "Bill.TotalAmount", New RectangleF(610, 409, 175, 18), 10.0!),
            CreateLabel("EmployeeName", "Bill.EmployeeName", New RectangleF(590, 445, 200, 20), 10.0!)
        })

        Return report
    End Function

    Private Shared Function CreateStaticLabel(ByVal name As String, ByVal text As String,
                                              ByVal bounds As RectangleF, ByVal fontSize As Single) As XRLabel
        Dim label As New XRLabel()
        label.Name = name
        label.BoundsF = bounds
        label.Font = New Font("Microsoft Sans Serif", fontSize)
        label.Padding = New DevExpress.XtraPrinting.PaddingInfo(0, 0, 0, 0, 100.0!)
        label.CanGrow = False
        label.Multiline = True
        label.WordWrap = True
        label.Text = text
        Return label
    End Function

    Private Shared Function CreateMoneyLabel(ByVal name As String, ByVal dataMember As String,
                                             ByVal bounds As RectangleF, ByVal fontSize As Single) As XRLabel
        Return CreateLabel(name, dataMember, bounds, fontSize, "{0:N2}")
    End Function

    Private Shared Function CreateLabel(ByVal name As String, ByVal dataMember As String,
                                        ByVal bounds As RectangleF, ByVal fontSize As Single,
                                        Optional ByVal formatString As String = Nothing) As XRLabel
        Dim label As New XRLabel()
        label.Name = name
        label.BoundsF = bounds
        label.Font = New Font("Microsoft Sans Serif", fontSize)
        label.Padding = New DevExpress.XtraPrinting.PaddingInfo(0, 0, 0, 0, 100.0!)
        label.CanGrow = False
        label.Multiline = True
        label.WordWrap = True
        label.Text = "[" & dataMember.Substring(dataMember.IndexOf("."c) + 1) & "]"

        If String.IsNullOrEmpty(formatString) Then
            label.DataBindings.Add(New XRBinding("Text", Nothing, dataMember))
        Else
            label.DataBindings.Add(New XRBinding("Text", Nothing, dataMember, formatString))
        End If

        Return label
    End Function
End Class
