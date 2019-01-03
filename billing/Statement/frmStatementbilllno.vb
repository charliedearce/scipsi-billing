Public Class frmStatementbilllno
    Protected Overrides ReadOnly Property CreateParams() As System.Windows.Forms.CreateParams
        Get
            Const DROPSHADOW = &H20000
            Dim cParam As CreateParams = MyBase.CreateParams
            cParam.ClassStyle = cParam.ClassStyle Or DROPSHADOW
            Return cParam
        End Get
    End Property
    Private Sub frmStatementbilllno_KeyDown(sender As Object, e As KeyEventArgs) Handles Me.KeyDown
        If (e.KeyCode = Keys.Enter) Then
            If txtBillnum.Text = "" Then
                MsgBox("Please Enter BILL number.", vbExclamation, Title:="Message")
            ElseIf txtService.Text = "" Then
                MsgBox("Please Enter Service Rendered.", vbExclamation, Title:="Message")
            Else
                ViewStatement()
                additemsatement()
                ViewStatement()
            End If
        ElseIf (e.KeyCode = Keys.Escape) Then
                frmStatement.txtNotice.Text = ""
                Me.Dispose()
        End If
    End Sub

    Private Sub frmStatementbilllno_Load(sender As Object, e As EventArgs) Handles MyBase.Load
        Me.KeyPreview = True
    End Sub

    Private Sub txtBillnum_KeyPress(sender As Object, e As KeyPressEventArgs) Handles txtBillnum.KeyPress
        If Asc(e.KeyChar) <> 8 Then
            If (Asc(e.KeyChar) < 48 Or Asc(e.KeyChar) > 57) Then
                e.Handled = True
            End If
        End If
    End Sub

    Private Sub txtBillnum_TextChanged(sender As Object, e As EventArgs) Handles txtBillnum.TextChanged

    End Sub

    Private Sub txtService_KeyPress(sender As Object, e As KeyPressEventArgs) Handles txtService.KeyPress
        If Asc(e.KeyChar) <> 8 And Asc(e.KeyChar) <> 32 And Asc(e.KeyChar) <> 35 And Asc(e.KeyChar) <> 46 And Asc(e.KeyChar) <> 47 And Asc(e.KeyChar) <> 40 And Asc(e.KeyChar) <> 41 And Asc(e.KeyChar) <> 37 And Asc(e.KeyChar) <> 38 And Asc(e.KeyChar) <> 36 And Asc(e.KeyChar) <> 60 And Asc(e.KeyChar) <> 61 And Asc(e.KeyChar) <> 62 And Asc(e.KeyChar) <> 63 And Asc(e.KeyChar) <> 45 Then
            If (Asc(e.KeyChar) < 48 Or Asc(e.KeyChar) > 57) And (Asc(e.KeyChar) < 65 Or Asc(e.KeyChar) > 90) And (Asc(e.KeyChar) < 97 Or Asc(e.KeyChar) > 122) Then
                e.Handled = True
            End If
        End If
    End Sub

    Private Sub txtService_TextChanged(sender As Object, e As EventArgs) Handles txtService.TextChanged

    End Sub

    Private Sub txtponum_KeyPress(sender As Object, e As KeyPressEventArgs) Handles txtponum.KeyPress
        If Asc(e.KeyChar) <> 8 Then
            If (Asc(e.KeyChar) < 48 Or Asc(e.KeyChar) > 57) Then
                e.Handled = True
            End If
        End If
    End Sub

    Private Sub txtponum_TextChanged(sender As Object, e As EventArgs) Handles txtponum.TextChanged

    End Sub
End Class