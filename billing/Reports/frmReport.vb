Imports System.Data
Imports System.Data.SqlClient
Imports CrystalDecisions.CrystalReports.Engine
Imports CrystalDecisions.Shared
Public Class frmReport



    Private Sub frmReport_Disposed(sender As Object, e As EventArgs) Handles Me.Disposed
        myCon.close()
    End Sub

    Private Sub frmReport_Load(sender As Object, e As EventArgs) Handles MyBase.Load
        Try


            myCon.open()

            Dim ds As New DSBill

            myCmd = New SqlCommand("BillRep", myCon)
            myCmd.Parameters.Add(New SqlParameter("@date_from", SqlDbType.DateTime)).Value = BillMonthSelectionReport.dtpFrom.Text
            myCmd.Parameters.Add(New SqlParameter("@date_to", SqlDbType.DateTime)).Value = BillMonthSelectionReport.dtpTo.Text
            myCmd.Parameters.Add(New SqlParameter("@employee", SqlDbType.VarChar)).Value = frmBilling.txtName.Text
            myCmd.CommandType = CommandType.StoredProcedure
            myAdapter.SelectCommand = myCmd
            myAdapter.Fill(ds, "BillRep")

            Dim crystal As New CrystalBillReport
            Dim objText As CrystalDecisions.CrystalReports.Engine.TextObject = crystal.ReportDefinition.Sections(1).ReportObjects("txtDate")
            objText.Text = "From: " + BillMonthSelectionReport.dtpFrom.Text + " To: " + BillMonthSelectionReport.dtpTo.Text

            crystal.SetDataSource(ds)
            CrystalReportViewer1.ReportSource = crystal
            CrystalReportViewer1.Refresh()
        Catch ex As Exception
            MsgBox("NO RECORD TO SHOW...")
            MsgBox(ex.Message)
            myCon.close()
            Me.Dispose()
        End Try
        myCon.close()
    End Sub
End Class