Imports System.Data.SqlClient

Public Class repBill

    Private Sub btn1_Click(sender As Object, e As EventArgs) Handles btn1.Click
        exportsunbill()

    End Sub

    Private Sub SimpleButton1_Click(sender As Object, e As EventArgs) Handles SimpleButton1.Click
        exportsunbill()

    End Sub

    Private Sub SimpleButton2_Click(sender As Object, e As EventArgs) Handles SimpleButton2.Click
        exportsunbill()
    End Sub

    Private Sub repBill_FormClosed(sender As Object, e As FormClosedEventArgs) Handles Me.FormClosed
        Me.Dispose()
    End Sub

    Private Sub repBill_Load(sender As Object, e As EventArgs) Handles MyBase.Load
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

    Private Sub txtcusper_KeyPress(sender As Object, e As KeyPressEventArgs) Handles txtcusper.KeyPress
        If Asc(e.KeyChar) <> 8 Then
            If (Asc(e.KeyChar) < 48 Or Asc(e.KeyChar) > 57) And (Asc(e.KeyChar) < 46 Or Asc(e.KeyChar) > 46) Then
                e.Handled = True
            End If
        End If
    End Sub

    Private Sub txtcusper_TextChanged(sender As Object, e As EventArgs) Handles txtcusper.TextChanged

    End Sub
End Class