Public Class frmORBillDelete
    Protected Overrides ReadOnly Property CreateParams() As System.Windows.Forms.CreateParams
        Get
            Const DROPSHADOW = &H20000
            Dim cParam As CreateParams = MyBase.CreateParams
            cParam.ClassStyle = cParam.ClassStyle Or DROPSHADOW
            Return cParam
        End Get
    End Property
    Private Sub frmORBillDelete_KeyDown(sender As Object, e As KeyEventArgs) Handles Me.KeyDown
        If (e.KeyCode = Keys.Escape) Then
            Me.Close()
            frmor.txtNotice.Text = ""
        ElseIf (e.KeyCode = Keys.Back) Then
            If txtIBillid.Text = "" Then
                MsgBox("Please Select Bill Transaction to delete, then press Enter", MsgBoxStyle.Information, "Action!")
            Else
                Dim result As Integer = MessageBox.Show("Are you sure you want to Delete this Bill # " + txtBillno.Text + "?", "Caution!", MessageBoxButtons.YesNo)

                If result = DialogResult.Yes Then
                    BillDelete()
                    vBillDelete()
                    viewORItems()
                End If

            End If
        End If
    End Sub

    Private Sub frmORBillDelete_Load(sender As Object, e As EventArgs) Handles MyBase.Load
        Me.KeyPreview = True
        vBillDelete()

    End Sub

    Private Sub dgDelete_SelectionChanged1(sender As Object, e As EventArgs) Handles dgDelete.SelectionChanged
        On Error Resume Next
        txtIBillid.Text = dgDelete.CurrentRow.Cells(0).Value.ToString
        txtBillno.Text = dgDelete.CurrentRow.Cells(1).Value.ToString
    End Sub
End Class