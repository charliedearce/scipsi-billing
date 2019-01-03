Public Class frmPercent 

    Private Sub frmPercent_Load(sender As Object, e As EventArgs) Handles MyBase.Load
        viewsettings()
    End Sub

    Private Sub vat_KeyPress(sender As Object, e As KeyPressEventArgs) Handles vat.KeyPress
        If Asc(e.KeyChar) <> 8 Then
            If (Asc(e.KeyChar) < 48 Or Asc(e.KeyChar) > 57) And (Asc(e.KeyChar) < 46 Or Asc(e.KeyChar) > 46) Then
                e.Handled = True
            End If
        End If
    End Sub

    Private Sub vat_TextChanged(sender As Object, e As EventArgs) Handles vat.TextChanged

    End Sub

    Private Sub fppa_KeyPress(sender As Object, e As KeyPressEventArgs) Handles fppa.KeyPress
        If Asc(e.KeyChar) <> 8 Then
            If (Asc(e.KeyChar) < 48 Or Asc(e.KeyChar) > 57) And (Asc(e.KeyChar) < 46 Or Asc(e.KeyChar) > 46) Then
                e.Handled = True
            End If
        End If
    End Sub

    Private Sub fppa_TextChanged(sender As Object, e As EventArgs) Handles fppa.TextChanged

    End Sub

    Private Sub dppa_KeyPress(sender As Object, e As KeyPressEventArgs) Handles dppa.KeyPress
        If Asc(e.KeyChar) <> 8 Then
            If (Asc(e.KeyChar) < 48 Or Asc(e.KeyChar) > 57) And (Asc(e.KeyChar) < 46 Or Asc(e.KeyChar) > 46) Then
                e.Handled = True
            End If
        End If
    End Sub

    Private Sub dppa_TextChanged(sender As Object, e As EventArgs) Handles dppa.TextChanged

    End Sub

    Private Sub whtax_KeyPress(sender As Object, e As KeyPressEventArgs) Handles whtax.KeyPress
        If Asc(e.KeyChar) <> 8 Then
            If (Asc(e.KeyChar) < 48 Or Asc(e.KeyChar) > 57) And (Asc(e.KeyChar) < 46 Or Asc(e.KeyChar) > 46) Then
                e.Handled = True
            End If
        End If
    End Sub

    Private Sub whtax_TextChanged(sender As Object, e As EventArgs) Handles whtax.TextChanged

    End Sub

    Private Sub disc_KeyPress(sender As Object, e As KeyPressEventArgs) Handles disc.KeyPress
        If Asc(e.KeyChar) <> 8 Then
            If (Asc(e.KeyChar) < 48 Or Asc(e.KeyChar) > 57) And (Asc(e.KeyChar) < 46 Or Asc(e.KeyChar) > 46) Then
                e.Handled = True
            End If
        End If
    End Sub


    Private Sub btn1_Click(sender As Object, e As EventArgs) Handles btn1.Click
        savesettings()
    End Sub
End Class