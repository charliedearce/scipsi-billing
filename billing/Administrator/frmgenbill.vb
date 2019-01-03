Public Class frmgenbill

    Private Sub btn1_Click(sender As Object, e As EventArgs) Handles btn1.Click
        generatebillnumber()
    End Sub

    Private Sub txtfrom_KeyPress(sender As Object, e As KeyPressEventArgs) Handles txtfrom.KeyPress
        If Asc(e.KeyChar) <> 8 Then
            If (Asc(e.KeyChar) < 48 Or Asc(e.KeyChar) > 57) And (Asc(e.KeyChar) < 46 Or Asc(e.KeyChar) > 46) Then
                e.Handled = True
            End If
        End If
    End Sub

    Private Sub txtfrom_TextChanged(sender As Object, e As EventArgs) Handles txtfrom.TextChanged

    End Sub

    Private Sub txtto_KeyPress(sender As Object, e As KeyPressEventArgs) Handles txtto.KeyPress
        If Asc(e.KeyChar) <> 8 Then
            If (Asc(e.KeyChar) < 48 Or Asc(e.KeyChar) > 57) And (Asc(e.KeyChar) < 46 Or Asc(e.KeyChar) > 46) Then
                e.Handled = True
            End If
        End If
    End Sub

    Private Sub txtto_TextChanged(sender As Object, e As EventArgs) Handles txtto.TextChanged

    End Sub

    Private Sub SimpleButton1_Click(sender As Object, e As EventArgs) Handles SimpleButton1.Click
        deletebillnumber()
    End Sub

    Private Sub txtsearch_KeyPress(sender As Object, e As KeyPressEventArgs) Handles txtsearch.KeyPress
        If Asc(e.KeyChar) <> 8 Then
            If (Asc(e.KeyChar) < 48 Or Asc(e.KeyChar) > 57) And (Asc(e.KeyChar) < 46 Or Asc(e.KeyChar) > 46) Then
                e.Handled = True
            End If
        End If
    End Sub

    Private Sub txtsearch_TextChanged(sender As Object, e As EventArgs) Handles txtsearch.TextChanged
        searchbillnumber()
    End Sub

    Private Sub frmgenbill_FormClosed(sender As Object, e As FormClosedEventArgs) Handles Me.FormClosed
        Me.Dispose()
    End Sub

    Private Sub frmbill_Load(sender As Object, e As EventArgs) Handles MyBase.Load
        viewbillnumber()
        If (myCon.State = ConnectionState.Open) Then
            myCon.close()
        End If
    End Sub

    Private Sub btnclear_Click(sender As Object, e As EventArgs) Handles btnclear.Click
        updatebillnumber()
    End Sub
End Class