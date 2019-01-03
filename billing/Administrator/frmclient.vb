Public Class frmclient

    Private Sub txtAccountno_KeyDown(sender As Object, e As KeyEventArgs) Handles txtAccountno.KeyDown

    End Sub

    Private Sub txtAccountno_KeyPress(sender As Object, e As KeyPressEventArgs) Handles txtAccountno.KeyPress

    End Sub

    Private Sub txtAccountno_MaskInputRejected(sender As Object, e As MaskInputRejectedEventArgs) Handles txtAccountno.MaskInputRejected

    End Sub

    Private Sub txtAccountno_TextChanged(sender As Object, e As EventArgs) Handles txtAccountno.TextChanged
        searchclient(txtAccountno.Text)
    End Sub

    Private Sub btn1_Click(sender As Object, e As EventArgs) Handles btn1.Click
        If btn1.Text = "Register" Then
            registerclient()
        ElseIf btn1.Text = "Update" Then
            Dim result As Integer = MessageBox.Show("Are you sure you want to update this Account #: " + txtAccountno.Text + "?", "Caution!", MessageBoxButtons.YesNo)
            If result = DialogResult.Yes Then
                updateclient()
            End If
        End If
    End Sub

    Private Sub btn2_Click(sender As Object, e As EventArgs) Handles btn2.Click
        Dim result As Integer = MessageBox.Show("Are you sure you want to delete this Account #: " + txtAccountno.Text + "?", "Caution!", MessageBoxButtons.YesNo)
        If result = DialogResult.Yes Then
            deleteclient()
        End If
    End Sub

    Private Sub frmclient_FormClosed(sender As Object, e As FormClosedEventArgs) Handles Me.FormClosed
        Me.Dispose()
    End Sub

    Private Sub frmclient_Load(sender As Object, e As EventArgs) Handles MyBase.Load
        If (myCon.State = ConnectionState.Open) Then
            myCon.close()
        End If
    End Sub
End Class