Imports System.Data.SqlClient

Public Class repVolume

    Private Sub repVolume_FormClosed(sender As Object, e As FormClosedEventArgs) Handles Me.FormClosed
        Me.Dispose()
    End Sub

    Private Sub repVolume_Load(sender As Object, e As EventArgs) Handles MyBase.Load

        myCon.open()
        Dim dt As New DataTable
        Dim ds As New DataSet
        ds.Tables.Add(dt)
        myCmd = New SqlCommand("SELECT * FROM tbl_bill_no", myCon)
        myAdapter = New SqlDataAdapter(myCmd)
        myAdapter.Fill(dt)
        myCmd.Dispose()
        myCon.close()
        If (myCon.State = ConnectionState.Open) Then
            myCon.close()
        End If
        For value As Integer = 0 To 12
            ' Exit condition if the value is three.
            cbPeriod.Items.Insert(value.ToString, Date.Now.ToString("yyyy") + "0" + value.ToString("00"))
        Next
    End Sub


    Private Sub txtvolume_KeyPress(sender As Object, e As KeyPressEventArgs) Handles txtvolume.KeyPress
        If Asc(e.KeyChar) <> 8 Then
            If (Asc(e.KeyChar) < 48 Or Asc(e.KeyChar) > 57) And (Asc(e.KeyChar) < 46 Or Asc(e.KeyChar) > 46) Then
                e.Handled = True
            End If
        End If
    End Sub

    Private Sub txtvolume_TextChanged(sender As Object, e As EventArgs) Handles txtvolume.TextChanged

    End Sub

    Private Sub btn1_Click_1(sender As Object, e As EventArgs) Handles btn1.Click
        frmVolume.Show()
        frmVolume.MdiParent = frmControlpanel
        Me.Dispose()
    End Sub

    Private Sub SimpleButton1_Click(sender As Object, e As EventArgs) Handles SimpleButton1.Click
        frmVolume.Show()
        frmVolume.MdiParent = frmControlpanel
        Me.Dispose()
    End Sub
End Class