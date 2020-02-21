Imports System.Data
Imports System.Data.SqlClient
Imports CrystalDecisions.CrystalReports.Engine
Imports CrystalDecisions.Shared
Imports System.IO
Public Class ORMonthSelectionReport

    Private Sub btnNewBill_Click(sender As Object, e As EventArgs) Handles btnNewBill.Click
        frmORReport.ShowDialog()
    End Sub

    Private Sub Button2_Click(sender As Object, e As EventArgs) Handles Button2.Click
        Try

            myCon.open()

            Dim ds As New DSBill

            myCmd = New SqlCommand("OrRep", myCon)
            myCmd.Parameters.Add(New SqlParameter("@employee", SqlDbType.VarChar)).Value = frmBilling.txtName.Text
            myCmd.Parameters.Add(New SqlParameter("@date_from", SqlDbType.DateTime)).Value = dtpFrom.Text
            myCmd.Parameters.Add(New SqlParameter("@date_to", SqlDbType.DateTime)).Value = dtpTo.Text
            myCmd.CommandType = CommandType.StoredProcedure
            myAdapter.SelectCommand = myCmd
            myAdapter.Fill(ds, "OrRep")

  

            Dim crystal As New CrystalOrBillReport
            Dim objText As CrystalDecisions.CrystalReports.Engine.TextObject = crystal.ReportDefinition.Sections(1).ReportObjects("txtDate")
            objText.Text = "From: " + dtpFrom.Text + " To: " + dtpTo.Text


            

            crystal.SetDataSource(ds)

            SaveFileDialog1.Filter = "Word files (*.doc)|*.doc"
            SaveFileDialog1.Title = "Save OR Report"
            SaveFileDialog1.FileName = "OR Report " + Date.Now.ToString("MM-dd-yyyy")
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

            myCmd = New SqlCommand("OrRep", myCon)
            myCmd.Parameters.Add(New SqlParameter("@employee", SqlDbType.VarChar)).Value = frmBilling.txtName.Text
            myCmd.Parameters.Add(New SqlParameter("@date_from", SqlDbType.DateTime)).Value = dtpFrom.Text
            myCmd.Parameters.Add(New SqlParameter("@date_to", SqlDbType.DateTime)).Value = dtpTo.Text
            myCmd.CommandType = CommandType.StoredProcedure
            myAdapter.SelectCommand = myCmd
            myAdapter.Fill(ds, "OrRep")

            Dim mycmd2 = New SqlCommand("SELECT COUNT(*) FROM tbl_or_trans WHERE or_status = 'INVALID' AND [or_date] BETWEEN '" & dtpFrom.Text & "' AND '" & dtpTo.Text & "'", myCon)
            Dim invalid = mycmd2.ExecuteNonQuery
            invalid = mycmd2.ExecuteScalar


            Dim crystal As New CrystalOrBillReport
            Dim objText As CrystalDecisions.CrystalReports.Engine.TextObject = crystal.ReportDefinition.Sections(1).ReportObjects("txtDate")
            objText.Text = "From: " + dtpFrom.Text + " To: " + dtpTo.Text





            crystal.SetDataSource(ds)

            SaveFileDialog1.Filter = "PDF files (*.pdf)|*.pdf"
            SaveFileDialog1.Title = "Save Bill Report"
            SaveFileDialog1.FileName = "OR Report " + Date.Now.ToString("MM-dd-yyyy")
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

    Private Sub Button1_Click(sender As Object, e As EventArgs) Handles Button1.Click
        Try
            myCon.open()
            Dim ds As New DSBill

            myCmd = New SqlCommand("OrRep", myCon)
            myCmd.Parameters.Add(New SqlParameter("@employee", SqlDbType.VarChar)).Value = frmBilling.txtName.Text
            myCmd.Parameters.Add(New SqlParameter("@date_from", SqlDbType.DateTime)).Value = dtpFrom.Text
            myCmd.Parameters.Add(New SqlParameter("@date_to", SqlDbType.DateTime)).Value = dtpTo.Text
            myCmd.CommandType = CommandType.StoredProcedure
            myAdapter.SelectCommand = myCmd
            myAdapter.Fill(ds, "OrRep")



            'Dim mycmd1 = New SqlCommand("SELECT COUNT(*) FROM tbl_or_trans WHERE or_status = 'VALID' AND [or_date] BETWEEN '" & dtpFrom.Text & "' AND '" & dtpTo.Text & "'", myCon)
            'Dim valid = mycmd1.ExecuteNonQuery
            'valid = mycmd1.ExecuteScalar

            'Dim mycmd2 = New SqlCommand("SELECT COUNT(*) FROM tbl_or_trans WHERE or_status = 'INVALID' AND [or_date] BETWEEN '" & dtpFrom.Text & "' AND '" & dtpTo.Text & "'", myCon)
            'Dim invalid = mycmd2.ExecuteNonQuery
            'invalid = mycmd2.ExecuteScalar

            'Dim mycmd3 = New SqlCommand("SELECT COUNT(*) FROM tbl_or_trans WHERE or_cash <> 0 AND or_status = 'VALID' AND [or_date] BETWEEN '" & dtpFrom.Text & "' AND '" & dtpTo.Text & "'", myCon)
            'Dim cash = mycmd3.ExecuteNonQuery
            'cash = mycmd3.ExecuteScalar

            'Dim mycmd4 = New SqlCommand("SELECT COUNT(*) FROM tbl_or_trans WHERE or_cash = 0 AND or_status = 'VALID' AND [or_date] BETWEEN '" & dtpFrom.Text & "' AND '" & dtpTo.Text & "'", myCon)
            'Dim check = mycmd4.ExecuteNonQuery
            'check = mycmd4.ExecuteScalar

            'Dim mycmd5 = New SqlCommand("SELECT COUNT(*) FROM tbl_or_trans WHERE or_discount <> 0 AND or_status = 'VALID' AND [or_date] BETWEEN '" & dtpFrom.Text & "' AND '" & dtpTo.Text & "'", myCon)
            'Dim disc = mycmd5.ExecuteNonQuery
            'disc = mycmd5.ExecuteScalar

            'Dim mycmd6 = New SqlCommand("SELECT SUM(or_bill_vat) FROM tbl_or_trans WHERE or_status = 'VALID' AND [or_date] BETWEEN '" & dtpFrom.Text & "' AND '" & dtpTo.Text & "'", myCon)
            'Dim vat = mycmd6.ExecuteNonQuery
            'vat = mycmd6.ExecuteScalar

            'Dim mycmd7 = New SqlCommand("SELECT SUM(or_bill_amount) FROM tbl_or_trans WHERE or_status = 'VALID' AND [or_date] BETWEEN '" & dtpFrom.Text & "' AND '" & dtpTo.Text & "'", myCon)
            'Dim mvat = mycmd7.ExecuteNonQuery
            'mvat = mycmd7.ExecuteScalar

            'Dim mycmd8 = New SqlCommand("SELECT SUM(or_htax) FROM tbl_or_trans WHERE or_status = 'VALID' AND [or_date] BETWEEN '" & dtpFrom.Text & "' AND '" & dtpTo.Text & "'", myCon)
            'Dim htax = mycmd8.ExecuteNonQuery
            'htax = mycmd8.ExecuteScalar

            'Dim mycmd9 = New SqlCommand("SELECT SUM(or_discount) FROM tbl_or_trans WHERE or_status = 'VALID' AND [or_date] BETWEEN '" & dtpFrom.Text & "' AND '" & dtpTo.Text & "'", myCon)
            'Dim mdisc = mycmd9.ExecuteNonQuery
            'mdisc = mycmd9.ExecuteScalar


            'Dim crystal As New OrExcel
            'Dim objText As CrystalDecisions.CrystalReports.Engine.TextObject = crystal.ReportDefinition.Sections(1).ReportObjects("txtDate")
            'objText.Text = "From: " + dtpFrom.Text + " To: " + dtpTo.Text

            'Dim objText1 As CrystalDecisions.CrystalReports.Engine.TextObject = crystal.ReportDefinition.Sections(4).ReportObjects("txtvalid")
            'objText1.Text = valid

            'Dim objText2 As CrystalDecisions.CrystalReports.Engine.TextObject = crystal.ReportDefinition.Sections(4).ReportObjects("txtinvalid")
            'objText2.Text = invalid

            'Dim objText3 As CrystalDecisions.CrystalReports.Engine.TextObject = crystal.ReportDefinition.Sections(4).ReportObjects("txtcash")
            'objText3.Text = cash

            'Dim objText4 As CrystalDecisions.CrystalReports.Engine.TextObject = crystal.ReportDefinition.Sections(4).ReportObjects("txtcheck")
            'objText4.Text = check

            'Dim objText5 As CrystalDecisions.CrystalReports.Engine.TextObject = crystal.ReportDefinition.Sections(4).ReportObjects("txtdisc")
            'objText5.Text = disc

            'Dim objText6 As CrystalDecisions.CrystalReports.Engine.TextObject = crystal.ReportDefinition.Sections(4).ReportObjects("txtvat")
            'objText6.Text = FormatNumber(CDbl(vat), TriState.True)

            'Dim objText7 As CrystalDecisions.CrystalReports.Engine.TextObject = crystal.ReportDefinition.Sections(4).ReportObjects("txtmvat")
            'objText7.Text = FormatNumber(CDbl(mvat), TriState.True)

            'Dim objText8 As CrystalDecisions.CrystalReports.Engine.TextObject = crystal.ReportDefinition.Sections(4).ReportObjects("txthtax")
            'objText8.Text = FormatNumber(CDbl(htax), TriState.True)

            'Dim objText9 As CrystalDecisions.CrystalReports.Engine.TextObject = crystal.ReportDefinition.Sections(4).ReportObjects("txtamt")
            'objText9.Text = FormatNumber(CDbl(mvat + htax), TriState.True)

            'Dim objText10 As CrystalDecisions.CrystalReports.Engine.TextObject = crystal.ReportDefinition.Sections(4).ReportObjects("txtmdisc")
            'objText10.Text = FormatNumber(CDbl(mdisc), TriState.True)

            'Dim objText11 As CrystalDecisions.CrystalReports.Engine.TextObject = crystal.ReportDefinition.Sections(4).ReportObjects("txtnet")
            'objText11.Text = FormatNumber(CDbl(objText9.Text - objText10.Text), TriState.True)


            Dim crystal As New OrExcel


            crystal.SetDataSource(ds)

            SaveFileDialog1.Filter = "Excel files (*.xls)|*.xls"
            SaveFileDialog1.Title = "Save OR Report"
            SaveFileDialog1.FileName = "OR Report " + Date.Now.ToString("MM-dd-yyyy")
            SaveFileDialog1.ShowDialog()
            Dim strExportFile As String = SaveFileDialog1.FileName

            Dim CrExportOptions As ExportOptions
            Dim CrDiskFileDestinationOptions As New _
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
            MsgBox("NO RECORD TO SHOW...")
            myCon.close()
        Finally
            If (myCon.State = ConnectionState.Open) Then
            myCon.close()
        End If
        End Try
        myCon.close()
    End Sub

    Private Sub ORMonthSelectionReport_Load(sender As Object, e As EventArgs) Handles MyBase.Load

    End Sub
End Class