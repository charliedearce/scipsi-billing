Public Class frmservice 

    Private Sub btn1_Click(sender As Object, e As EventArgs) Handles btn1.Click
        If btn1.Text = "Register" Then
            registerservice()
        ElseIf btn1.Text = "Update" Then
            Dim result As Integer = MessageBox.Show("Are you sure you want to update this Service code: " + txtServiceCode.Text + "?", "Caution!", MessageBoxButtons.YesNo)
            If result = DialogResult.Yes Then
                updateservice()
            End If
        End If
    End Sub


    Private Sub txtServiceCode_TextChanged(sender As Object, e As EventArgs) Handles txtServiceCode.TextChanged
        searchservice()
    End Sub

    Private Sub btn2_Click(sender As Object, e As EventArgs) Handles btn2.Click
        Dim result As Integer = MessageBox.Show("Are you sure you want to Delete this Service code: " + txtServiceCode.Text + "?", "Caution!", MessageBoxButtons.YesNo)
        If result = DialogResult.Yes Then
            deleteservice()
        End If
    End Sub

    Private Sub frmservice_FormClosed(sender As Object, e As FormClosedEventArgs) Handles Me.FormClosed
        Me.Dispose()
    End Sub

    Private Sub frmservice_Load(sender As Object, e As EventArgs) Handles MyBase.Load
        If (myCon.State = ConnectionState.Open) Then
            myCon.close()
        End If
    End Sub
End Class