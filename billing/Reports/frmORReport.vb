Imports System.Data
Imports System.Data.SqlClient
Imports CrystalDecisions.CrystalReports.Engine
Imports CrystalDecisions.Shared

Public Class frmORReport
    Private Sub frmORReport_Disposed(sender As Object, e As EventArgs) Handles Me.Disposed
        myCon.close()
    End Sub

    Private Sub frmORReport_Load(sender As Object, e As EventArgs) Handles MyBase.Load

        Try


            myCon.open()

            Dim ds As New DSBill

            myCmd = New SqlCommand("OrRep", myCon)
            myCmd.Parameters.Add(New SqlParameter("@date_from", SqlDbType.DateTime)).Value = ORMonthSelectionReport.dtpFrom.Text
            myCmd.Parameters.Add(New SqlParameter("@date_to", SqlDbType.DateTime)).Value = ORMonthSelectionReport.dtpTo.Text
            myCmd.Parameters.Add(New SqlParameter("@employee", SqlDbType.VarChar)).Value = frmBilling.txtName.Text
            myCmd.CommandType = CommandType.StoredProcedure
            myAdapter.SelectCommand = myCmd
            myAdapter.Fill(ds, "OrRep")


            Dim crystal As New CrystalOrBillReport
            Dim objText As CrystalDecisions.CrystalReports.Engine.TextObject = crystal.ReportDefinition.Sections(1).ReportObjects("txtDate")
            objText.Text = "From: " + ORMonthSelectionReport.dtpFrom.Text + " To: " + ORMonthSelectionReport.dtpTo.Text




            crystal.SetDataSource(ds)
            CrystalReportViewer1.ReportSource = crystal
            CrystalReportViewer1.Refresh()
            myCon.close()
        Catch ex As Exception
            MsgBox("NO RECORD TO SHOW...")
            myCon.close()
            Me.Dispose()
        End Try

    End Sub

End Class







