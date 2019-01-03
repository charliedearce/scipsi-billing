<Global.Microsoft.VisualBasic.CompilerServices.DesignerGenerated()> _
Partial Class BillMonthSelectionReport
    Inherits System.Windows.Forms.Form

    'Form overrides dispose to clean up the component list.
    <System.Diagnostics.DebuggerNonUserCode()> _
    Protected Overrides Sub Dispose(ByVal disposing As Boolean)
        Try
            If disposing AndAlso components IsNot Nothing Then
                components.Dispose()
            End If
        Finally
            MyBase.Dispose(disposing)
        End Try
    End Sub

    'Required by the Windows Form Designer
    Private components As System.ComponentModel.IContainer

    'NOTE: The following procedure is required by the Windows Form Designer
    'It can be modified using the Windows Form Designer.  
    'Do not modify it using the code editor.
    <System.Diagnostics.DebuggerStepThrough()> _
    Private Sub InitializeComponent()
        Me.dtpFrom = New System.Windows.Forms.DateTimePicker()
        Me.Label1 = New System.Windows.Forms.Label()
        Me.Label2 = New System.Windows.Forms.Label()
        Me.dtpTo = New System.Windows.Forms.DateTimePicker()
        Me.SaveFileDialog1 = New System.Windows.Forms.SaveFileDialog()
        Me.Button3 = New System.Windows.Forms.Button()
        Me.Button2 = New System.Windows.Forms.Button()
        Me.Button1 = New System.Windows.Forms.Button()
        Me.btnNewBill = New System.Windows.Forms.Button()
        Me.SuspendLayout()
        '
        'dtpFrom
        '
        Me.dtpFrom.CustomFormat = "MM/dd/yyyy"
        Me.dtpFrom.Font = New System.Drawing.Font("Arial Narrow", 18.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.dtpFrom.Format = System.Windows.Forms.DateTimePickerFormat.Custom
        Me.dtpFrom.Location = New System.Drawing.Point(103, 24)
        Me.dtpFrom.Name = "dtpFrom"
        Me.dtpFrom.Size = New System.Drawing.Size(122, 35)
        Me.dtpFrom.TabIndex = 2
        Me.dtpFrom.Value = New Date(2014, 7, 27, 0, 0, 0, 0)
        '
        'Label1
        '
        Me.Label1.AutoSize = True
        Me.Label1.BackColor = System.Drawing.Color.White
        Me.Label1.Font = New System.Drawing.Font("Arial Narrow", 20.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label1.Location = New System.Drawing.Point(12, 27)
        Me.Label1.Name = "Label1"
        Me.Label1.Size = New System.Drawing.Size(85, 31)
        Me.Label1.TabIndex = 26
        Me.Label1.Text = "FROM:"
        '
        'Label2
        '
        Me.Label2.AutoSize = True
        Me.Label2.BackColor = System.Drawing.Color.White
        Me.Label2.Font = New System.Drawing.Font("Arial Narrow", 20.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label2.Location = New System.Drawing.Point(231, 27)
        Me.Label2.Name = "Label2"
        Me.Label2.Size = New System.Drawing.Size(51, 31)
        Me.Label2.TabIndex = 28
        Me.Label2.Text = "TO:"
        '
        'dtpTo
        '
        Me.dtpTo.CustomFormat = "MM/dd/yyyy"
        Me.dtpTo.Font = New System.Drawing.Font("Arial Narrow", 18.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.dtpTo.Format = System.Windows.Forms.DateTimePickerFormat.Custom
        Me.dtpTo.Location = New System.Drawing.Point(288, 24)
        Me.dtpTo.Name = "dtpTo"
        Me.dtpTo.Size = New System.Drawing.Size(121, 35)
        Me.dtpTo.TabIndex = 27
        Me.dtpTo.Value = New Date(2014, 7, 27, 0, 0, 0, 0)
        '
        'Button3
        '
        Me.Button3.BackColor = System.Drawing.Color.Transparent
        Me.Button3.BackgroundImageLayout = System.Windows.Forms.ImageLayout.None
        Me.Button3.FlatStyle = System.Windows.Forms.FlatStyle.Flat
        Me.Button3.Image = Global.billing.My.Resources.Resources.pdf_48
        Me.Button3.ImageAlign = System.Drawing.ContentAlignment.TopCenter
        Me.Button3.Location = New System.Drawing.Point(712, 4)
        Me.Button3.Name = "Button3"
        Me.Button3.Size = New System.Drawing.Size(93, 74)
        Me.Button3.TabIndex = 32
        Me.Button3.TabStop = False
        Me.Button3.Text = "PDF"
        Me.Button3.TextAlign = System.Drawing.ContentAlignment.BottomCenter
        Me.Button3.UseVisualStyleBackColor = False
        '
        'Button2
        '
        Me.Button2.BackColor = System.Drawing.Color.Transparent
        Me.Button2.BackgroundImageLayout = System.Windows.Forms.ImageLayout.None
        Me.Button2.FlatStyle = System.Windows.Forms.FlatStyle.Flat
        Me.Button2.Image = Global.billing.My.Resources.Resources.word_48
        Me.Button2.ImageAlign = System.Drawing.ContentAlignment.TopCenter
        Me.Button2.Location = New System.Drawing.Point(613, 4)
        Me.Button2.Name = "Button2"
        Me.Button2.Size = New System.Drawing.Size(93, 74)
        Me.Button2.TabIndex = 31
        Me.Button2.TabStop = False
        Me.Button2.Text = "MS WORD"
        Me.Button2.TextAlign = System.Drawing.ContentAlignment.BottomCenter
        Me.Button2.UseVisualStyleBackColor = False
        '
        'Button1
        '
        Me.Button1.BackColor = System.Drawing.Color.Transparent
        Me.Button1.BackgroundImageLayout = System.Windows.Forms.ImageLayout.None
        Me.Button1.FlatStyle = System.Windows.Forms.FlatStyle.Flat
        Me.Button1.Image = Global.billing.My.Resources.Resources.excel_48
        Me.Button1.ImageAlign = System.Drawing.ContentAlignment.TopCenter
        Me.Button1.Location = New System.Drawing.Point(514, 4)
        Me.Button1.Name = "Button1"
        Me.Button1.Size = New System.Drawing.Size(93, 74)
        Me.Button1.TabIndex = 30
        Me.Button1.TabStop = False
        Me.Button1.Text = "MS EXCEL"
        Me.Button1.TextAlign = System.Drawing.ContentAlignment.BottomCenter
        Me.Button1.UseVisualStyleBackColor = False
        '
        'btnNewBill
        '
        Me.btnNewBill.BackColor = System.Drawing.Color.Transparent
        Me.btnNewBill.BackgroundImageLayout = System.Windows.Forms.ImageLayout.None
        Me.btnNewBill.FlatStyle = System.Windows.Forms.FlatStyle.Flat
        Me.btnNewBill.Image = Global.billing.My.Resources.Resources.View_Details_48
        Me.btnNewBill.ImageAlign = System.Drawing.ContentAlignment.TopCenter
        Me.btnNewBill.Location = New System.Drawing.Point(415, 4)
        Me.btnNewBill.Name = "btnNewBill"
        Me.btnNewBill.Size = New System.Drawing.Size(93, 74)
        Me.btnNewBill.TabIndex = 29
        Me.btnNewBill.TabStop = False
        Me.btnNewBill.Text = "PREVIEW"
        Me.btnNewBill.TextAlign = System.Drawing.ContentAlignment.BottomCenter
        Me.btnNewBill.UseVisualStyleBackColor = False
        '
        'BillMonthSelectionReport
        '
        Me.AutoScaleDimensions = New System.Drawing.SizeF(6.0!, 13.0!)
        Me.AutoScaleMode = System.Windows.Forms.AutoScaleMode.Font
        Me.BackColor = System.Drawing.Color.White
        Me.ClientSize = New System.Drawing.Size(816, 82)
        Me.Controls.Add(Me.Button3)
        Me.Controls.Add(Me.Button2)
        Me.Controls.Add(Me.Button1)
        Me.Controls.Add(Me.btnNewBill)
        Me.Controls.Add(Me.Label2)
        Me.Controls.Add(Me.dtpTo)
        Me.Controls.Add(Me.Label1)
        Me.Controls.Add(Me.dtpFrom)
        Me.FormBorderStyle = System.Windows.Forms.FormBorderStyle.FixedToolWindow
        Me.MaximizeBox = False
        Me.MinimizeBox = False
        Me.Name = "BillMonthSelectionReport"
        Me.ShowIcon = False
        Me.ShowInTaskbar = False
        Me.StartPosition = System.Windows.Forms.FormStartPosition.CenterParent
        Me.Text = "SELECT DATE TO GENERATE BILL REPORT"
        Me.ResumeLayout(False)
        Me.PerformLayout()

    End Sub
    Friend WithEvents dtpFrom As System.Windows.Forms.DateTimePicker
    Friend WithEvents Label1 As System.Windows.Forms.Label
    Friend WithEvents Label2 As System.Windows.Forms.Label
    Friend WithEvents dtpTo As System.Windows.Forms.DateTimePicker
    Friend WithEvents btnNewBill As System.Windows.Forms.Button
    Friend WithEvents Button1 As System.Windows.Forms.Button
    Friend WithEvents SaveFileDialog1 As System.Windows.Forms.SaveFileDialog
    Friend WithEvents Button2 As System.Windows.Forms.Button
    Friend WithEvents Button3 As System.Windows.Forms.Button
End Class
