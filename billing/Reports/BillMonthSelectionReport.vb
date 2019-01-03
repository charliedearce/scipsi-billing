Imports System.Data
Imports System.Data.SqlClient
Imports CrystalDecisions.CrystalReports.Engine
Imports CrystalDecisions.Shared
Imports System.IO

Public Class BillMonthSelectionReport

    Private Sub btnNewBill_Click(sender As Object, e As EventArgs) Handles btnNewBill.Click

        frmReport.Show()

    End Sub

    Private Sub Button1_Click(sender As Object, e As EventArgs) Handles Button1.Click
        Try

            myCon.open()

            Dim ds As New DSBill

            myCmd = New SqlCommand("BillRep", myCon)
            myCmd.Parameters.Add(New SqlParameter("@employee", SqlDbType.VarChar)).Value = frmBilling.txtName.Text
            myCmd.Parameters.Add(New SqlParameter("@date_from", SqlDbType.DateTime)).Value = dtpFrom.Text
            myCmd.Parameters.Add(New SqlParameter("@date_to", SqlDbType.DateTime)).Value = dtpTo.Text
            myCmd.CommandType = CommandType.StoredProcedure
            myAdapter.SelectCommand = myCmd
            myAdapter.Fill(ds, "BillRep")

            'Dim mycmd1 = New SqlCommand("SELECT COUNT(*) FROM tbl_bill_trans WHERE bt_status = 'VALID' AND [bt_date] BETWEEN '" & dtpFrom.Text & "' AND '" & dtpTo.Text & "'", myCon)
            'Dim valid = mycmd1.ExecuteNonQuery
            'valid = mycmd1.ExecuteScalar

            'Dim mycmd2 = New SqlCommand("SELECT COUNT(*) FROM tbl_bill_trans WHERE bt_status = 'INVALID' AND [bt_date] BETWEEN '" & dtpFrom.Text & "' AND '" & dtpTo.Text & "'", myCon)
            'Dim invalid = mycmd2.ExecuteNonQuery
            'invalid = mycmd2.ExecuteScalar


            'Dim mycmd6 = New SqlCommand("SELECT SUM(bt_total) FROM tbl_bill_trans WHERE bt_status = 'VALID' AND [bt_date] BETWEEN '" & dtpFrom.Text & "' AND '" & dtpTo.Text & "'", myCon)
            'Dim total = mycmd6.ExecuteNonQuery
            'total = mycmd6.ExecuteScalar

            'Dim mycmd7 = New SqlCommand("SELECT SUM(bt_ppa) FROM tbl_bill_trans WHERE bt_status = 'VALID' AND [bt_date] BETWEEN '" & dtpFrom.Text & "' AND '" & dtpTo.Text & "'", myCon)
            'Dim ppa = mycmd7.ExecuteNonQuery
            'ppa = mycmd7.ExecuteScalar

            'Dim mycmd8 = New SqlCommand("SELECT SUM(bt_net) FROM tbl_bill_trans WHERE bt_status = 'VALID' AND [bt_date] BETWEEN '" & dtpFrom.Text & "' AND '" & dtpTo.Text & "'", myCon)
            'Dim net = mycmd8.ExecuteNonQuery
            'net = mycmd8.ExecuteScalar

            'Dim mycmd9 = New SqlCommand("SELECT SUM(bt_vat) FROM tbl_bill_trans WHERE bt_status = 'VALID' AND [bt_date] BETWEEN '" & dtpFrom.Text & "' AND '" & dtpTo.Text & "'", myCon)
            'Dim vat = mycmd9.ExecuteNonQuery
            'vat = mycmd9.ExecuteScalar

            'Dim mycmd10 = New SqlCommand("SELECT SUM(bt_scipsi) FROM tbl_bill_trans WHERE bt_status = 'VALID' AND [bt_date] BETWEEN '" & dtpFrom.Text & "' AND '" & dtpTo.Text & "'", myCon)
            'Dim scipsi = mycmd10.ExecuteNonQuery
            'scipsi = mycmd10.ExecuteScalar


            '
            'Dim objText As CrystalDecisions.CrystalReports.Engine.TextObject = crystal.ReportDefinition.Sections(1).ReportObjects("txtDate")
            'objText.Text = "From: " + dtpFrom.Text + " To: " + dtpTo.Text

            'Dim objText1 As CrystalDecisions.CrystalReports.Engine.TextObject = crystal.ReportDefinition.Sections(4).ReportObjects("txtvalid")
            'objText1.Text = valid

            'Dim objText2 As CrystalDecisions.CrystalReports.Engine.TextObject = crystal.ReportDefinition.Sections(4).ReportObjects("txtinvalid")
            'objText2.Text = invalid

            'Dim objText3 As CrystalDecisions.CrystalReports.Engine.TextObject = crystal.ReportDefinition.Sections(4).ReportObjects("txttotal")
            'objText3.Text = FormatNumber(CDbl(total), TriState.True)

            'Dim objText4 As CrystalDecisions.CrystalReports.Engine.TextObject = crystal.ReportDefinition.Sections(4).ReportObjects("txtppa")
            'objText4.Text = FormatNumber(CDbl(ppa), TriState.True)

            'Dim objText5 As CrystalDecisions.CrystalReports.Engine.TextObject = crystal.ReportDefinition.Sections(4).ReportObjects("txtnet")
            'objText5.Text = FormatNumber(CDbl(net), TriState.True)

            'Dim objText6 As CrystalDecisions.CrystalReports.Engine.TextObject = crystal.ReportDefinition.Sections(4).ReportObjects("txtvat")
            'objText6.Text = FormatNumber(CDbl(vat), TriState.True)

            'Dim objText7 As CrystalDecisions.CrystalReports.Engine.TextObject = crystal.ReportDefinition.Sections(4).ReportObjects("txtscipsi")
            'objText7.Text = FormatNumber(CDbl(scipsi), TriState.True)
            Dim crystal As New BillExcel
            crystal.SetDataSource(ds)

            SaveFileDialog1.Filter = "Excel files (*.xls)|*.xls"
            SaveFileDialog1.Title = "Save Bill Report"
            SaveFileDialog1.FileName = "Bill Report " + Date.Now.ToString("MM-dd-yyyy")
            SaveFileDialog1.ShowDialog()
            Dim strExportFile As String = SaveFileDialog1.FileName

            Dim CrExportOptions As ExportOptions
            Dim CrDiskFileDestinationOptions As New  _
            DiskFileDestinationOptions()
            Dim CrFormatTypeOptions As New ExcelFormatOptions
            CrDiskFileDestinationOptions.DiskFileName = strExportFile

            CrExportOptions = crystal.ExportOptions
            With CrExportOptions
                .ExportDestinationType = ExportDestinationType.DiskFile
                .ExportFormatType = ExportFormatType.ExcelWorkbook
                .DestinationOptions = CrDiskFileDestinationOptions
                .FormatOptions = CrFormatTypeOptions
            End With
            crystal.Export()

        Catch ex As Exception
            MsgBox(ex.Message)
        Finally
            If (myCon.State = ConnectionState.Open) Then
                myCon.close()
            End If
        End Try
        myCon.close()
    End Sub

    Private Sub Button2_Click(sender As Object, e As EventArgs) Handles Button2.Click
        Try

            myCon.open()

            Dim ds As New DSBill

            myCmd = New SqlCommand("BillRep", myCon)
            myCmd.Parameters.Add(New SqlParameter("@employee", SqlDbType.VarChar)).Value = frmBilling.txtName.Text
            myCmd.Parameters.Add(New SqlParameter("@date_from", SqlDbType.DateTime)).Value = dtpFrom.Text
            myCmd.Parameters.Add(New SqlParameter("@date_to", SqlDbType.DateTime)).Value = dtpTo.Text
            myCmd.CommandType = CommandType.StoredProcedure
            myAdapter.SelectCommand = myCmd
            myAdapter.Fill(ds, "BillRep")



            Dim crystal As New CrystalBillReport
            Dim objText As CrystalDecisions.CrystalReports.Engine.TextObject = crystal.ReportDefinition.Sections(1).ReportObjects("txtDate")
            objText.Text = "From: " + dtpFrom.Text + " To: " + dtpTo.Text

         
            crystal.SetDataSource(ds)

            SaveFileDialog1.Filter = "Word files (*.doc)|*.doc"
            SaveFileDialog1.Title = "Save Bill Report"
            SaveFileDialog1.FileName = "Bill Report " + Date.Now.ToString("MM-dd-yyyy")
            SaveFileDialog1.ShowDialog()
            Dim strExportFile As String = SaveFileDialog1.FileName

            Dim CrExportOptions As ExportOptions
            Dim CrDiskFileDestinationOptions As New  _
            DiskFileDestinationOptions()
            Dim CrFormatTypeOptions As New PdfRtfWordFormatOptions
            CrDiskFileDestinationOptions.DiskFileName = strExportFile

            CrExportOptions = crystal.ExportOptions
            With CrExportOptions
                .ExportDestinationType = ExportDestinationType.DiskFile
                .ExportFormatType = ExportFormatType.WordForWindows
                .DestinationOptions = CrDiskFileDestinationOptions
                .FormatOptions = CrFormatTypeOptions
            End With
            crystal.Export()

        Catch ex As Exception
            MsgBox("NO RECORD TO SHOW...")
            myCon.close()
        Finally
            If (myCon.State = ConnectionState.Open) Then
                myCon.close()
            End If
        End Try
        myCon.close()
    End Sub

    Private Sub Button3_Click(sender As Object, e As EventArgs) Handles Button3.Click
        Try

            myCon.open()

            Dim ds As New DSBill

            myCmd = New SqlCommand("BillRep", myCon)
            myCmd.Parameters.Add(New SqlParameter("@employee", SqlDbType.VarChar)).Value = frmBilling.txtName.Text
            myCmd.Parameters.Add(New SqlParameter("@date_from", SqlDbType.DateTime)).Value = dtpFrom.Text
            myCmd.Parameters.Add(New SqlParameter("@date_to", SqlDbType.DateTime)).Value = dtpTo.Text
            myCmd.CommandType = CommandType.StoredProcedure
            myAdapter.SelectCommand = myCmd
            myAdapter.Fill(ds, "BillRep")



            Dim crystal As New CrystalBillReport
            Dim objText As CrystalDecisions.CrystalReports.Engine.TextObject = crystal.ReportDefinition.Sections(1).ReportObjects("txtDate")
            objText.Text = "From: " + dtpFrom.Text + " To: " + dtpTo.Text

          

            SaveFileDialog1.Filter = "PDF files (*.pdf)|*.pdf"
            SaveFileDialog1.Title = "Save Bill Report"
            SaveFileDialog1.FileName = "Bill Report " + Date.Now.ToString("MM-dd-yyyy")
            SaveFileDialog1.ShowDialog()
            Dim strExportFile As String = SaveFileDialog1.FileName

            Dim CrExportOptions As ExportOptions
            Dim CrDiskFileDestinationOptions As New  _
            DiskFileDestinationOptions()
            Dim CrFormatTypeOptions As New PdfFormatOptions
            CrDiskFileDestinationOptions.DiskFileName = strExportFile

            CrExportOptions = crystal.ExportOptions
            With CrExportOptions
                .ExportDestinationType = ExportDestinationType.DiskFile
                .ExportFormatType = ExportFormatType.PortableDocFormat
                .DestinationOptions = CrDiskFileDestinationOptions
                .FormatOptions = CrFormatTypeOptions
            End With
            crystal.Export()

        Catch ex As Exception
            MsgBox("NO RECORD TO SHOW...")
            myCon.close()
        Finally
            If (myCon.State = ConnectionState.Open) Then
                myCon.close()
            End If
        End Try
        myCon.close()
    End Sub

    Private Sub BillMonthSelectionReport_Load(sender As Object, e As EventArgs) Handles MyBase.Load

    End Sub
End Class