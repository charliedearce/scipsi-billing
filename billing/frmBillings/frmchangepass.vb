Public Class frmchangepass 
    Protected Overrides ReadOnly Property CreateParams() As System.Windows.Forms.CreateParams
        Get
            Const DROPSHADOW = &H20000
            Dim cParam As CreateParams = MyBase.CreateParams
            cParam.ClassStyle = cParam.ClassStyle Or DROPSHADOW
            Return cParam
        End Get
    End Property
    Private Sub btnUserDel_Click(sender As Object, e As EventArgs) Handles btnUserDel.Click
        If txtpassword.Text = "" Or txtretypepass.Text = "" Then
            MsgBox("Please fill up all fields.", vbExclamation, "Message")
        ElseIf txtpassword.Text <> txtretypepass.Text Then
            MsgBox("Password not match, Please try again.", vbExclamation, "Message")
        Else
            Dim result As Integer = MessageBox.Show("Are you sure you want to change your password?", "Caution!", MessageBoxButtons.YesNo)
            If result = DialogResult.Yes Then
                changepassword()
                MsgBox("Password Successfully Changed.", vbInformation, "Message")
                Me.Dispose()
            End If
        End If
    End Sub

    Private Sub frmchangepass_KeyDown(sender As Object, e As KeyEventArgs) Handles Me.KeyDown
        If (e.KeyCode = Keys.Escape) Then
            Me.Dispose()
        Else
            If (e.KeyCode = Keys.Enter) Then
                btnUserDel_Click(sender, e)
            End If
        End If
    End Sub

    Private Sub frmchangepass_Load(sender As Object, e As EventArgs) Handles MyBase.Load
        Me.KeyPreview = True
        txtpassword.Focus()
    End Sub
End Class