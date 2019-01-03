
Imports System.Data.SqlClient

Public Class frmVolume
    

    Private Sub frmVolume_FormClosed(sender As Object, e As FormClosedEventArgs) Handles Me.FormClosed
        Me.Dispose()
    End Sub
    Private Sub frmVolume_Load(sender As Object, e As EventArgs) Handles MyBase.Load
        On Error Resume Next
        Dim a As Integer
        If repVolume.XtraTabControl1.SelectedTabPageIndex = 0 Then
            a = repVolume.cbPeriod.Text
        ElseIf repVolume.XtraTabControl1.SelectedTabPageIndex = 1 Then
            a = repVolume.txtvolume.Text
        End If
        TextBox1.Text = a
        myCon.open()
        Dim crystal As New volume
        Dim ds As New DSBill
        myCmd = New SqlCommand("Volume", myCon)
        myCmd.Parameters.Add(New SqlParameter("@period", SqlDbType.BigInt)).Value = TextBox1.Text
        myCmd.CommandType = CommandType.StoredProcedure
        myAdapter.SelectCommand = myCmd

        myAdapter.Fill(ds, "Volume")

        crystal.SetDataSource(ds)
        CrystalReportViewer1.ReportSource = crystal
        CrystalReportViewer1.Refresh()
        myCon.close()
    End Sub

End Class