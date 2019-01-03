Public Class frmORBillno
    Protected Overrides ReadOnly Property CreateParams() As System.Windows.Forms.CreateParams
        Get
            Const DROPSHADOW = &H20000
            Dim cParam As CreateParams = MyBase.CreateParams
            cParam.ClassStyle = cParam.ClassStyle Or DROPSHADOW
            Return cParam
        End Get
    End Property
    Private Sub frmORBillno_KeyDown(sender As Object, e As KeyEventArgs) Handles Me.KeyDown
        If (e.KeyCode = Keys.Enter) Then

            If txtORBillno.Text = "" Then
                txtORBillno.Focus()
                frmor.txtNotice.Text = "Please Enter Bill number!"
            Else
                Dim change As Integer = txtORBillno.Text
                txtORBillno.Text = String.Format("{0:0000000000}", change.ToString("0000000000"))
                Dim exists As Boolean = False
                For Each itm As DataGridViewRow In frmor.dgOrItems.Rows
                    If itm.Cells(1).Value = txtORBillno.Text Then
                        exists = True
                    End If
                Next

                If exists = True Then
                    frmor.txtNotice.Text = "This Bill number is on Process."
                Else
                    orBillNo()
                    viewORItems()

                    If frmor.dgOrItems.Rows.Count > 11 Then
                        frmor.btnAddOr.Enabled = False
                        frmor.txtNotice.Text = "Your OR transaction has reach to its maximum items."
                    End If
                End If
            End If
        ElseIf (e.KeyCode = Keys.Escape) Then
            frmor.txtNotice.Text = ""
            txtORBillno.Text = ""
            Me.Dispose()
        End If
    End Sub

    Private Sub frmORBillno_Load(sender As Object, e As EventArgs) Handles MyBase.Load
        Me.KeyPreview = True

    End Sub

    Private Sub txtORBillno_KeyPress(sender As Object, e As KeyPressEventArgs) Handles txtORBillno.KeyPress
        If Asc(e.KeyChar) <> 8 Then
            If (Asc(e.KeyChar) < 48 Or Asc(e.KeyChar) > 57) Then
                e.Handled = True
            End If
        End If
    End Sub

    Private Sub txtORBillno_TextChanged(sender As Object, e As EventArgs) Handles txtORBillno.TextChanged

    End Sub
End Class