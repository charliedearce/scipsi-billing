Public Class frmUser 

    Private Sub txtRegUsername_TextChanged(sender As Object, e As EventArgs) Handles txtRegUsername.TextChanged
        If txtRegUsername.Text <> "" Then
            alreadyuser()
        End If
    End Sub

    Private Sub frmUser_FormClosed(sender As Object, e As FormClosedEventArgs) Handles Me.FormClosed
        Me.Dispose()
    End Sub

    Private Sub frmUser_Load(sender As Object, e As EventArgs) Handles MyBase.Load
        If (myCon.State = ConnectionState.Open) Then
            myCon.close()
        End If
        cbRegRole.Text = "USER"
    End Sub

    Private Sub btn1_Click(sender As Object, e As EventArgs) Handles btn1.Click
        If btn1.Text = "Register" Then
            uservalidation()
        ElseIf btn1.Text = "Update" Then
            uservalidation()
        End If
    End Sub

    Private Sub btn2_Click(sender As Object, e As EventArgs) Handles btn2.Click
        clearuser()
    End Sub

    Private Sub btndelete_Click(sender As Object, e As EventArgs) Handles btndelete.Click
        deleteuser()
    End Sub
End Class