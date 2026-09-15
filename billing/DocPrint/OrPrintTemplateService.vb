Imports System.Configuration
Imports System.Data
Imports System.Data.SqlClient
Imports System.Drawing
Imports System.Drawing.Printing
Imports System.Globalization
Imports System.IO
Imports System.Windows.Forms
Imports DevExpress.XtraReports.UI

Public NotInheritable Class OrPrintTemplateService
    Private Const TemplateFileName As String = "OfficialReceipt.repx"
    Private Const TemplateDirectoryName As String = "SCIPSI Billing\Templates"

    Private Sub New()
    End Sub

    Public Shared ReadOnly Property TemplatePath As String
        Get
            Dim configuredPath As String = ConfigurationManager.AppSettings("OrPrintTemplatePath")
            If Not String.IsNullOrWhiteSpace(configuredPath) Then
                Return Environment.ExpandEnvironmentVariables(configuredPath)
            End If

            Return Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData), TemplateDirectoryName, TemplateFileName)
        End Get
    End Property

    Public Shared Sub DesignTemplate(ByVal owner As IWin32Window)
        Try
            Using data As DataSet = OrPrintDataProvider.CreateSampleData()
                Using report As XtraReport = CreateBoundReport(data, True)
                    Using designTool As New ReportDesignTool(report)
                        designTool.ShowDesignerDialog()
                    End Using

                    If MessageBox.Show(owner,
                                       "Save this layout as the active Official Receipt template?" & Environment.NewLine & Environment.NewLine & TemplatePath,
                                       "OR Print Designer",
                                       MessageBoxButtons.YesNo,
                                       MessageBoxIcon.Question) = DialogResult.Yes Then
                        SaveTemplate(report)
                        MessageBox.Show(owner, "Official Receipt template saved.", "OR Print Designer", MessageBoxButtons.OK, MessageBoxIcon.Information)
                    End If
                End Using
            End Using
        Catch ex As Exception
            MessageBox.Show(owner, "Unable to open or save the OR template designer." & Environment.NewLine & ex.Message,
                            "OR Print Designer", MessageBoxButtons.OK, MessageBoxIcon.Error)
        End Try
    End Sub

    Public Shared Function PrintReceipt(ByVal receiptNumber As String, ByVal owner As IWin32Window) As Boolean
        If String.IsNullOrWhiteSpace(receiptNumber) Then
            MessageBox.Show(owner, "An Official Receipt number is required before printing.", "Print Official Receipt", MessageBoxButtons.OK, MessageBoxIcon.Warning)
            Return False
        End If

        Try
            Using data As DataSet = OrPrintDataProvider.LoadReceipt(receiptNumber)
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
                            MarkReceiptPrinted(receiptNumber)
                        End If

                        Return printRequested
                    End Using
                End Using
            End Using
        Catch ex As Exception
            MessageBox.Show(owner, "Unable to print Official Receipt " & receiptNumber & "." & Environment.NewLine & ex.Message,
                            "Print Official Receipt", MessageBoxButtons.OK, MessageBoxIcon.Error)
            Return False
        End Try
    End Function

    Private Shared Function CreateBoundReport(ByVal data As DataSet, ByVal warnOnInvalidTemplate As Boolean) As XtraReport
        Dim report As XtraReport = OrPrintReportFactory.CreateDefaultReport()

        If File.Exists(TemplatePath) Then
            Try
                report.LoadLayout(TemplatePath)
            Catch ex As Exception
                report.Dispose()
                report = OrPrintReportFactory.CreateDefaultReport()
                If warnOnInvalidTemplate Then
                    MessageBox.Show("The saved OR template could not be loaded. The default layout will be opened instead." & Environment.NewLine & ex.Message,
                                    "OR Print Designer", MessageBoxButtons.OK, MessageBoxIcon.Warning)
                End If
            End Try
        End If

        BindData(report, data)
        Return report
    End Function

    Private Shared Sub BindData(ByVal report As XtraReport, ByVal data As DataSet)
        report.DataSource = data
        report.DataMember = "Receipt"
        report.ScriptsSource = String.Empty

        BindAllocationBands(report.Bands, data)
    End Sub

    Private Shared Sub BindAllocationBands(ByVal bands As BandCollection, ByVal data As DataSet)
        For Each band As Band In bands
            Dim detailBand As DetailReportBand = TryCast(band, DetailReportBand)
            If detailBand Is Nothing Then
                Continue For
            End If

            If String.Equals(detailBand.Name, "AllocationsBand", StringComparison.OrdinalIgnoreCase) OrElse
               (detailBand.DataMember IsNot Nothing AndAlso detailBand.DataMember.EndsWith("ReceiptAllocations", StringComparison.OrdinalIgnoreCase)) Then
                detailBand.DataSource = data
                detailBand.DataMember = "Receipt.ReceiptAllocations"
            End If

            BindAllocationBands(detailBand.Bands, data)
        Next
    End Sub

    Private Shared Sub SaveTemplate(ByVal report As XtraReport)
        Dim directoryPath As String = Path.GetDirectoryName(TemplatePath)
        If String.IsNullOrWhiteSpace(directoryPath) Then
            Throw New InvalidOperationException("The OR print template path must include a directory.")
        End If

        Directory.CreateDirectory(directoryPath)
        If File.Exists(TemplatePath) Then
            Dim backupPath As String = Path.Combine(directoryPath,
                                                    "OfficialReceipt-" & DateTime.Now.ToString("yyyyMMdd-HHmmss", CultureInfo.InvariantCulture) & ".repx.backup")
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

    Private Shared Sub MarkReceiptPrinted(ByVal receiptNumber As String)
        Using connection As New SqlConnection(ConfigurationManager.ConnectionStrings("ConString").ConnectionString)
            Using command As New SqlCommand("UPDATE tbl_or_trans SET or_indiprint = 'Y' WHERE or_num = @or_num", connection)
                command.Parameters.Add("@or_num", SqlDbType.VarChar, 20).Value = receiptNumber
                connection.Open()
                command.ExecuteNonQuery()
            End Using
        End Using
    End Sub
End Class

Friend NotInheritable Class OrPrintDataProvider
    Private Sub New()
    End Sub

    Public Shared Function LoadReceipt(ByVal receiptNumber As String) As DataSet
        Dim data As DataSet = CreateSchema()
        Dim receipt As DataTable = data.Tables("Receipt")
        Dim allocations As DataTable = data.Tables("Allocations")

        Using connection As New SqlConnection(ConfigurationManager.ConnectionStrings("ConString").ConnectionString)
            connection.Open()

            Using command As New SqlCommand("SELECT TOP (1) or_num, or_acc_num, or_accname, or_remarks, or_date, or_employee, " &
                                            "or_bill_vat, or_bill_amount, or_htax, or_net, or_check_num, or_cash, or_period, or_bank, " &
                                            "or_bstyle, or_tin, or_baddress " &
                                            "FROM tbl_or_trans WHERE or_num = @or_num", connection)
                command.Parameters.Add("@or_num", SqlDbType.VarChar, 20).Value = receiptNumber
                Using reader As SqlDataReader = command.ExecuteReader()
                    If Not reader.Read() Then
                        Throw New InvalidOperationException("The saved Official Receipt was not found. Save the receipt before printing it.")
                    End If

                    Dim billVat As Decimal = ReadDecimal(reader, "or_bill_vat")
                    Dim billAmount As Decimal = ReadDecimal(reader, "or_bill_amount")
                    Dim netAmount As Decimal = ReadDecimal(reader, "or_net")
                    Dim cash As Decimal = ReadDecimal(reader, "or_cash")
                    Dim row As DataRow = receipt.NewRow()
                    row("ReceiptNumber") = ReadString(reader, "or_num")
                    row("AccountNumber") = ReadString(reader, "or_acc_num")
                    row("CustomerName") = ReadString(reader, "or_accname")
                    row("Remarks") = ReadString(reader, "or_remarks")
                    row("ReceiptDate") = ReadDate(reader, "or_date")
                    row("EmployeeName") = ReadString(reader, "or_employee")
                    row("BillVat") = billVat
                    row("BillAmount") = billAmount
                    row("WithholdingTax") = ReadDecimal(reader, "or_htax")
                    row("NetAmount") = netAmount
                    row("CheckNumber") = ReadString(reader, "or_check_num")
                    row("CashTendered") = cash
                    row("ChangeAmount") = Math.Max(0D, cash - netAmount)
                    row("Period") = ReadString(reader, "or_period")
                    row("BankCode") = ReadString(reader, "or_bank")
                    row("BusinessStyle") = ReadString(reader, "or_bstyle")
                    row("Tin") = ReadString(reader, "or_tin")
                    row("Address") = ReadString(reader, "or_baddress")
                    row("VatSales") = If(billVat <> 0D, billAmount - billVat, 0D)
                    row("ZeroRatedSales") = If(billVat = 0D, billAmount, 0D)
                    row("TotalSales") = billAmount
                    row("AmountInWords") = SpellNumber(netAmount.ToString("0.00", CultureInfo.InvariantCulture))
                    receipt.Rows.Add(row)
                End Using
            End Using

            Using command As New SqlCommand("SELECT ot_bill_no, ot_bill_vat, ot_scipsi, ot_citw, ot_disc, ot_net, " &
                                            "ot_partial, ot_balance, ot_indipartial " &
                                            "FROM tbl_orbill_trans WHERE ot_or_no = @or_num ORDER BY ot_id", connection)
                command.Parameters.Add("@or_num", SqlDbType.VarChar, 20).Value = receiptNumber
                Using reader As SqlDataReader = command.ExecuteReader()
                    While reader.Read()
                        Dim row As DataRow = allocations.NewRow()
                        row("ReceiptNumber") = receiptNumber
                        row("BillNumber") = ReadString(reader, "ot_bill_no")
                        row("BillVat") = ReadDecimal(reader, "ot_bill_vat")
                        row("BillAmount") = ReadDecimal(reader, "ot_scipsi")
                        row("WithholdingTax") = ReadDecimal(reader, "ot_citw")
                        row("Discount") = ReadDecimal(reader, "ot_disc")
                        row("NetAmount") = ReadDecimal(reader, "ot_net")
                        row("PrintedAmount") = ReadDecimal(reader, "ot_net") + ReadDecimal(reader, "ot_citw")
                        row("PartialAmount") = ReadDecimal(reader, "ot_partial")
                        row("Balance") = ReadDecimal(reader, "ot_balance")
                        row("PaymentKind") = ReadString(reader, "ot_indipartial")
                        allocations.Rows.Add(row)
                    End While
                End Using
            End Using
        End Using

        SetLineText(data)
        Return data
    End Function

    Public Shared Function CreateSampleData() As DataSet
        Dim data As DataSet = CreateSchema()
        Dim receipt As DataRow = data.Tables("Receipt").NewRow()
        receipt("ReceiptNumber") = "0000001234"
        receipt("AccountNumber") = "11001-0001"
        receipt("CustomerName") = "SAMPLE CUSTOMER"
        receipt("Remarks") = "Sample layout data"
        receipt("ReceiptDate") = Date.Today
        receipt("EmployeeName") = "SAMPLE CASHIER"
        receipt("BillVat") = 120D
        receipt("BillAmount") = 1120D
        receipt("WithholdingTax") = 20D
        receipt("NetAmount") = 1100D
        receipt("CheckNumber") = "CHK-0001"
        receipt("CashTendered") = 1200D
        receipt("ChangeAmount") = 100D
        receipt("Period") = Date.Today.ToString("yyyyMM", CultureInfo.InvariantCulture)
        receipt("BankCode") = "CASH"
        receipt("BusinessStyle") = "PORT SERVICES"
        receipt("Tin") = "000-000-000"
        receipt("Address") = "Sample address"
        receipt("VatSales") = 1000D
        receipt("ZeroRatedSales") = 0D
        receipt("TotalSales") = 1120D
        receipt("AmountInWords") = "One Thousand One Hundred Pesos Only"
        data.Tables("Receipt").Rows.Add(receipt)

        AddSampleAllocation(data.Tables("Allocations"), "0000001234", "0000004321", 560D)
        AddSampleAllocation(data.Tables("Allocations"), "0000001234", "0000004322", 560D)
        SetLineText(data)
        Return data
    End Function

    Private Shared Sub AddSampleAllocation(ByVal table As DataTable, ByVal receiptNumber As String,
                                           ByVal billNumber As String, ByVal amount As Decimal)
        Dim row As DataRow = table.NewRow()
        row("ReceiptNumber") = receiptNumber
        row("BillNumber") = billNumber
        row("BillVat") = 60D
        row("BillAmount") = amount
        row("WithholdingTax") = 10D
        row("Discount") = 0D
        row("NetAmount") = amount - 10D
        row("PrintedAmount") = amount
        row("PartialAmount") = 0D
        row("Balance") = 0D
        row("PaymentKind") = "F"
        table.Rows.Add(row)
    End Sub

    Private Shared Function CreateSchema() As DataSet
        Dim data As New DataSet("OfficialReceiptData")
        Dim receipt As New DataTable("Receipt")
        receipt.Columns.Add("ReceiptNumber", GetType(String))
        receipt.Columns.Add("AccountNumber", GetType(String))
        receipt.Columns.Add("CustomerName", GetType(String))
        receipt.Columns.Add("Remarks", GetType(String))
        receipt.Columns.Add("ReceiptDate", GetType(DateTime))
        receipt.Columns.Add("EmployeeName", GetType(String))
        receipt.Columns.Add("BillVat", GetType(Decimal))
        receipt.Columns.Add("BillAmount", GetType(Decimal))
        receipt.Columns.Add("WithholdingTax", GetType(Decimal))
        receipt.Columns.Add("NetAmount", GetType(Decimal))
        receipt.Columns.Add("CheckNumber", GetType(String))
        receipt.Columns.Add("CashTendered", GetType(Decimal))
        receipt.Columns.Add("ChangeAmount", GetType(Decimal))
        receipt.Columns.Add("Period", GetType(String))
        receipt.Columns.Add("BankCode", GetType(String))
        receipt.Columns.Add("BusinessStyle", GetType(String))
        receipt.Columns.Add("Tin", GetType(String))
        receipt.Columns.Add("Address", GetType(String))
        receipt.Columns.Add("VatSales", GetType(Decimal))
        receipt.Columns.Add("ZeroRatedSales", GetType(Decimal))
        receipt.Columns.Add("TotalSales", GetType(Decimal))
        receipt.Columns.Add("AmountInWords", GetType(String))
        receipt.Columns.Add("BillLines", GetType(String))
        receipt.Columns.Add("AmountLines", GetType(String))
        receipt.PrimaryKey = New DataColumn() {receipt.Columns("ReceiptNumber")}

        Dim allocations As New DataTable("Allocations")
        allocations.Columns.Add("ReceiptNumber", GetType(String))
        allocations.Columns.Add("BillNumber", GetType(String))
        allocations.Columns.Add("BillVat", GetType(Decimal))
        allocations.Columns.Add("BillAmount", GetType(Decimal))
        allocations.Columns.Add("WithholdingTax", GetType(Decimal))
        allocations.Columns.Add("Discount", GetType(Decimal))
        allocations.Columns.Add("NetAmount", GetType(Decimal))
        allocations.Columns.Add("PrintedAmount", GetType(Decimal))
        allocations.Columns.Add("PartialAmount", GetType(Decimal))
        allocations.Columns.Add("Balance", GetType(Decimal))
        allocations.Columns.Add("PaymentKind", GetType(String))

        data.Tables.Add(receipt)
        data.Tables.Add(allocations)
        data.Relations.Add("ReceiptAllocations", receipt.Columns("ReceiptNumber"), allocations.Columns("ReceiptNumber"), False)
        Return data
    End Function

    Private Shared Sub SetLineText(ByVal data As DataSet)
        Dim billNumbers As New List(Of String)()
        Dim amounts As New List(Of String)()
        For Each row As DataRow In data.Tables("Allocations").Rows
            billNumbers.Add(Convert.ToString(row("BillNumber"), CultureInfo.InvariantCulture))
            amounts.Add(Convert.ToDecimal(row("PrintedAmount"), CultureInfo.InvariantCulture).ToString("N2"))
        Next

        If data.Tables("Receipt").Rows.Count > 0 Then
            data.Tables("Receipt").Rows(0)("BillLines") = String.Join(Environment.NewLine, billNumbers.ToArray())
            data.Tables("Receipt").Rows(0)("AmountLines") = String.Join(Environment.NewLine, amounts.ToArray())
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

Friend NotInheritable Class OrPrintReportFactory
    Private Sub New()
    End Sub

    Public Shared Function CreateDefaultReport() As XtraReport
        Dim report As New XtraReport()
        report.Name = "OfficialReceipt"
        report.DisplayName = "Official Receipt"
        report.Dpi = 100.0!
        report.PaperKind = PaperKind.Letter
        report.Margins = New Margins(0, 0, 0, 0)
        report.ShowPrintMarginsWarning = False

        Dim topMargin As New TopMarginBand()
        topMargin.HeightF = 0.0!
        Dim detail As New DetailBand()
        detail.Name = "ReceiptBand"
        detail.HeightF = 600.0!
        Dim bottomMargin As New BottomMarginBand()
        bottomMargin.HeightF = 0.0!
        report.Bands.AddRange(New Band() {topMargin, detail, bottomMargin})

        detail.Controls.AddRange(New XRControl() {
            CreateLabel("ReceiptDate", "Receipt.ReceiptDate", New RectangleF(605, 135, 180, 20), 10.0!, "{0:MM/dd/yyyy}"),
            CreateLabel("CustomerName", "Receipt.CustomerName", New RectangleF(165, 173, 560, 20), 10.0!),
            CreateLabel("Address", "Receipt.Address", New RectangleF(110, 193, 470, 18), 9.0!),
            CreateLabel("BusinessStyle", "Receipt.BusinessStyle", New RectangleF(130, 208, 420, 18), 9.0!),
            CreateLabel("Tin", "Receipt.Tin", New RectangleF(590, 208, 190, 18), 9.0!),
            CreateLabel("AmountInWords", "Receipt.AmountInWords", New RectangleF(130, 235, 520, 35), 8.0!),
            CreateMoneyLabel("NetAmountMain", "Receipt.NetAmount", New RectangleF(140, 255, 170, 20), 10.0!),
            CreateLabel("BillLines", "Receipt.BillLines", New RectangleF(90, 310, 165, 170), 10.0!),
            CreateLabel("AmountLines", "Receipt.AmountLines", New RectangleF(260, 310, 165, 170), 10.0!),
            CreateMoneyLabel("VatSales", "Receipt.VatSales", New RectangleF(540, 300, 180, 20), 10.0!),
            CreateMoneyLabel("ZeroRatedSales", "Receipt.ZeroRatedSales", New RectangleF(540, 340, 180, 20), 10.0!),
            CreateMoneyLabel("BillVat", "Receipt.BillVat", New RectangleF(540, 360, 180, 20), 10.0!),
            CreateMoneyLabel("TotalSales", "Receipt.TotalSales", New RectangleF(540, 380, 180, 20), 10.0!),
            CreateLabel("CheckNumber", "Receipt.CheckNumber", New RectangleF(625, 415, 160, 20), 10.0!),
            CreateMoneyLabel("SummaryBillAmount", "Receipt.BillAmount", New RectangleF(170, 495, 150, 12), 7.0!),
            CreateMoneyLabel("SummaryBillVat", "Receipt.BillVat", New RectangleF(170, 507, 150, 12), 7.0!),
            CreateMoneyLabel("SummaryVatExclusive", "Receipt.VatSales", New RectangleF(170, 519, 150, 12), 7.0!),
            CreateMoneyLabel("SummaryTotalSales", "Receipt.TotalSales", New RectangleF(170, 531, 150, 12), 7.0!),
            CreateMoneyLabel("SummaryWithholdingTax", "Receipt.WithholdingTax", New RectangleF(170, 543, 150, 12), 7.0!),
            CreateMoneyLabel("SummaryNetAmount", "Receipt.NetAmount", New RectangleF(170, 555, 150, 12), 7.0!),
            CreateLabel("EmployeeName", "Receipt.EmployeeName", New RectangleF(530, 500, 250, 20), 10.0!)
        })

        Return report
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
