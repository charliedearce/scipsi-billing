Imports System.Data.SqlClient

Public Class frmPrintYtrans

    Private Sub frmPrintYtrans_FormClosed(sender As Object, e As FormClosedEventArgs) Handles Me.FormClosed
        If (myCon.State = ConnectionState.Open) Then
            myCon.close()
        End If
    End Sub

    Private Sub frmPrintYtrans_Load(sender As Object, e As EventArgs) Handles MyBase.Load
        'On Error Resume Next
        Try
            myCon.open()
            Dim crystal As New YellowTrans

            Dim billdate As Date = frmYtrans.txtBilldate.Text

            Dim objText As CrystalDecisions.CrystalReports.Engine.TextObject = crystal.ReportDefinition.Sections(1).ReportObjects("txtdate")
            objText.Text = Format(billdate, "MMM dd, yyyy")

            Dim objText1 As CrystalDecisions.CrystalReports.Engine.TextObject = crystal.ReportDefinition.Sections(1).ReportObjects("txttransno")
            objText1.Text = frmYtrans.txtYtransno.Text

            Dim ds As New DSBill

            myCmd = New SqlCommand("ytans", myCon)
            myCmd.Parameters.Add(New SqlParameter("@transno", SqlDbType.Int)).Value = frmYtrans.txtYtransno.Text
            myCmd.CommandType = CommandType.StoredProcedure
            myAdapter.SelectCommand = myCmd
            myAdapter.Fill(ds, "ytans")

            crystal.SetDataSource(ds)
            CrystalReportViewer1.ReportSource = crystal
            CrystalReportViewer1.Refresh()
            myCmd.Dispose()
            myCon.close()
        Catch ex As Exception
            MsgBox("No Record to show...", vbInformation, "Message")
        Finally
            If (myCon.State = ConnectionState.Open) Then
                myCon.close()
            End If
        End Try

    End Sub
End Class