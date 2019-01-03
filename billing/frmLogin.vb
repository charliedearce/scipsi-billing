Imports System
Imports System.Globalization
Imports System.Windows.Forms
Imports AutoUpdaterDotNET
Public Class frmLogin

    ' TODO: Insert code to perform custom authentication using the provided username and password 
    ' (See http://go.microsoft.com/fwlink/?LinkId=35339).  
    ' The custom principal can then be attached to the current thread's principal as follows: 
    '     My.User.CurrentPrincipal = CustomPrincipal
    ' where CustomPrincipal is the IPrincipal implementation used to perform authentication. 
    ' Subsequently, My.User will return identity information encapsulated in the CustomPrincipal object
    ' such as the username, display name, etc.



    Private Sub btnLogin_Click(sender As Object, e As EventArgs) Handles btnLogin.Click
        If txtUname.Text = "" Or txtPass.Text = "" Then
            MsgBox("Please Fill up the feilds.", MsgBoxStyle.Critical, "Invalid!")
            txtUname.Focus()
        Else
            mylogin()
        End If

    End Sub



    Private Sub frmLogin_KeyDown(sender As Object, e As KeyEventArgs) Handles Me.KeyDown
        If (e.KeyCode = Keys.Enter) Then
            btnLogin_Click(sender, e)
        ElseIf (e.KeyCode = Keys.Escape) Then
            End
        End If
    End Sub

    Private Sub frmLogin_Load(sender As Object, e As EventArgs) Handles MyBase.Load
        cleanduplicates()
        autocomplete()
        Me.KeyPreview = True
        AutoUpdater.Start("http://192.168.10.29/update/update.xml")

    End Sub

    Private Sub LogoPictureBox_Click(sender As Object, e As EventArgs) Handles LogoPictureBox.Click
        About.ShowDialog()
    End Sub
End Class
