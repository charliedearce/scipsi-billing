Public Class frmgenor

    Private Sub txtfrom_KeyPress(sender As Object, e As KeyPressEventArgs)
        If Asc(e.KeyChar) <> 8 Then
            If (Asc(e.KeyChar) < 48 Or Asc(e.KeyChar) > 57) And (Asc(e.KeyChar) < 46 Or Asc(e.KeyChar) > 46) Then
                e.Handled = True
            End If
        End If
    End Sub

    Private Sub txtfrom_TextChanged(sender As Object, e As EventArgs)

    End Sub

    Private Sub txtto_KeyPress(sender As Object, e As KeyPressEventArgs)
        If Asc(e.KeyChar) <> 8 Then
            If (Asc(e.KeyChar) < 48 Or Asc(e.KeyChar) > 57) And (Asc(e.KeyChar) < 46 Or Asc(e.KeyChar) > 46) Then
                e.Handled = True
            End If
        End If
    End Sub

    Private Sub txtto_TextChanged(sender As Object, e As EventArgs)

    End Sub

    Private Sub txtsearch_KeyPress(sender As Object, e As KeyPressEventArgs)
        If Asc(e.KeyChar) <> 8 Then
            If (Asc(e.KeyChar) < 48 Or Asc(e.KeyChar) > 57) And (Asc(e.KeyChar) < 46 Or Asc(e.KeyChar) > 46) Then
                e.Handled = True
            End If
        End If
    End Sub

    Private Sub frmgenor_FormClosed(sender As Object, e As FormClosedEventArgs) Handles Me.FormClosed
        Me.Dispose()
    End Sub



    Private Sub frmgenor_Load(sender As Object, e As EventArgs) Handles MyBase.Load
        viewornumber()
        If (myCon.State = ConnectionState.Open) Then
            myCon.close()
        End If
    End Sub

    Private Sub btn1_Click_1(sender As Object, e As EventArgs) Handles btn1.Click
        generateORnumber()
    End Sub

    Private Sub SimpleButton1_Click_1(sender As Object, e As EventArgs) Handles SimpleButton1.Click
        deleteornumber()
    End Sub

    Private Sub btnclear_Click_1(sender As Object, e As EventArgs) Handles btnclear.Click
        updateornumber()
    End Sub

    Private Sub txtsearch_TextChanged_1(sender As Object, e As EventArgs) Handles txtsearch.TextChanged
        searchornumber()
    End Sub

    Private Sub txtfrom_KeyPress1(sender As Object, e As KeyPressEventArgs) Handles txtfrom.KeyPress
        If Asc(e.KeyChar) <> 8 Then
            If (Asc(e.KeyChar) < 48 Or Asc(e.KeyChar) > 57) And (Asc(e.KeyChar) < 46 Or Asc(e.KeyChar) > 46) Then
                e.Handled = True
            End If
        End If
    End Sub


    Private Sub txtfrom_TextChanged_1(sender As Object, e As EventArgs) Handles txtfrom.TextChanged

    End Sub

    Private Sub txtto_KeyPress1(sender As Object, e As KeyPressEventArgs) Handles txtto.KeyPress
        If Asc(e.KeyChar) <> 8 Then
            If (Asc(e.KeyChar) < 48 Or Asc(e.KeyChar) > 57) And (Asc(e.KeyChar) < 46 Or Asc(e.KeyChar) > 46) Then
                e.Handled = True
            End If
        End If
    End Sub

    Private Sub txtto_TextChanged_1(sender As Object, e As EventArgs) Handles txtto.TextChanged

    End Sub
End Class